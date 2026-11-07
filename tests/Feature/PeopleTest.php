<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\JournalEntry;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Hr;
use App\Support\Payroll;
use App\Support\Reports;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Employees, attendance, leave, reviews and payroll (admin side). Books are kept in PKR. */
class PeopleTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(RoleSeeder::class);
        Setting::put('accounting', ['base_currency' => 'PKR', 'fiscal_year_start' => 7, 'lock_date' => null]);

        $this->accountant = User::factory()->create();
        $this->accountant->roles()->attach(Role::where('slug', 'accountant')->value('id'));
        $this->manager = User::factory()->create();
        $this->manager->roles()->attach(Role::where('slug', 'manager')->value('id'));
    }

    private function employee(array $overrides = []): Employee
    {
        return Employee::create([
            'code' => Employee::nextCode(),
            'name' => 'Ayesha Khan',
            'email' => 'ayesha'.uniqid().'@example.com',
            'joined_on' => '2026-01-05',
            'basic_salary_cents' => 10000000, // PKR 100,000
            'shift_start' => '09:00:00',
            'shift_end' => '18:00:00',
            ...$overrides,
        ]);
    }

    public function test_salary_is_only_shown_to_people_with_payroll_permission(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->manager)->getJson("/admin/api/hr/employees/{$employee->id}")
            ->assertOk()->assertJsonMissingPath('data.basic_salary')->assertJsonPath('data.code', $employee->code);

        $this->actingAs($this->accountant)->getJson("/admin/api/hr/employees/{$employee->id}")
            ->assertOk()->assertJsonPath('data.basic_salary', 100000);
    }

    public function test_employee_codes_count_up_from_001(): void
    {
        $this->actingAs($this->manager)->postJson('/admin/api/hr/employees', $this->payload())->assertCreated()->assertJsonPath('data.code', 'EMP-001');
        $this->actingAs($this->manager)->postJson('/admin/api/hr/employees', $this->payload(['email' => 'two@example.com']))->assertJsonPath('data.code', 'EMP-002');
    }

    private function payload(array $overrides = []): array
    {
        return [
            'name' => 'Omar Farooq', 'email' => 'omar@example.com', 'joined_on' => '2026-02-01', 'basic_salary' => '100000',
            'shift_start' => '09:00', 'shift_end' => '18:00', ...$overrides,
        ];
    }

    public function test_check_in_is_late_only_after_the_grace_period(): void
    {
        $employee = $this->employee();

        // 09:10 in Pakistan is inside the 15-minute grace: on time.
        $this->assertSame(0, Hr::lateMinutes($employee, Carbon::parse('2026-10-19 09:10:00', 'Asia/Karachi')));
        // 09:40 is 40 minutes after the shift start.
        $this->assertSame(40, Hr::lateMinutes($employee, Carbon::parse('2026-10-19 09:40:00', 'Asia/Karachi')));
        // Early arrivals are never late.
        $this->assertSame(0, Hr::lateMinutes($employee, Carbon::parse('2026-10-19 08:30:00', 'Asia/Karachi')));
    }

    public function test_leave_balance_uses_the_allowance_and_approvals_reduce_it(): void
    {
        $employee = $this->employee();
        $today = Carbon::parse('2026-10-16', 'Asia/Karachi');

        $annual = fn () => collect(Hr::leaveBalances($employee, $today))->firstWhere('type', 'annual');
        $this->assertSame(14.0, $annual()['remaining']);

        $leave = LeaveRequest::create(['employee_id' => $employee->id, 'type' => 'annual', 'start_date' => '2026-10-20', 'end_date' => '2026-10-22', 'days' => 3, 'status' => 'pending']);
        $this->actingAs($this->manager)->postJson("/admin/api/hr/leave/{$leave->id}/decide", ['decision' => 'approved'])->assertOk()->assertJsonPath('data.status', 'approved');

        $this->assertSame(11.0, $annual()['remaining']);
    }

    public function test_leave_cannot_be_approved_beyond_the_balance(): void
    {
        $employee = $this->employee();
        $leave = LeaveRequest::create(['employee_id' => $employee->id, 'type' => 'sick', 'start_date' => '2026-10-20', 'end_date' => '2026-10-30', 'days' => 11, 'status' => 'pending']);

        $this->actingAs($this->manager)->postJson("/admin/api/hr/leave/{$leave->id}/decide", ['decision' => 'approved'])
            ->assertUnprocessable()->assertJsonFragment(['message' => 'Not enough sick leave left for this request.']);
    }

    public function test_unpaid_leave_is_deducted_and_tax_is_withheld_from_the_gross(): void
    {
        $employee = $this->employee();
        LeaveRequest::create(['employee_id' => $employee->id, 'type' => 'unpaid', 'start_date' => '2026-10-05', 'end_date' => '2026-10-07', 'days' => 3, 'status' => 'approved']);

        $run = PayrollRun::create(['period' => '2026-10']);
        $slip = Payroll::generate($run)->slips->first();

        // 3 unpaid days of 30: PKR 10,000 off PKR 100,000 leaves PKR 90,000 gross.
        $this->assertEquals(3.0, $slip->unpaid_days);
        $this->assertSame(9000000, $slip->gross_cents);
        // Annual 1,080,000: 1% on the 480,000 above 600,000 = 4,800, i.e. PKR 400 a month.
        $this->assertSame(40000, $slip->tax_cents);
        $this->assertSame(8960000, $slip->net_cents);
    }

    public function test_posting_payroll_books_salary_and_tax_and_locks_the_month(): void
    {
        $this->employee(['basic_salary_cents' => 10000000]);
        $this->employee(['basic_salary_cents' => 6000000]);
        $run = PayrollRun::create(['period' => '2026-10']);
        Payroll::generate($run);

        $this->actingAs($this->accountant)->postJson("/admin/api/payroll/runs/{$run->id}/post")->assertOk()->assertJsonPath('data.status', 'posted');

        // Salary expense equals gross pay; net pay and withheld tax are what the books owe.
        $entry = JournalEntry::whereMorphedTo('source', $run)->sole();
        $salaries = $entry->lines()->whereHas('account', fn ($q) => $q->where('code', '5000'))->sum('debit_cents');
        $this->assertSame($run->fresh()->gross_cents, (int) $salaries);
        $this->assertSame($run->fresh()->net_cents, (int) $entry->lines()->whereHas('account', fn ($q) => $q->where('code', '2300'))->sum('credit_cents'));
        $this->assertTrue(Reports::trialBalance('2026-10-31')['balanced']);

        // Posted months cannot be regenerated or deleted.
        $this->actingAs($this->accountant)->postJson("/admin/api/payroll/runs/{$run->id}/generate")->assertUnprocessable();
        $this->actingAs($this->accountant)->deleteJson("/admin/api/payroll/runs/{$run->id}")->assertUnprocessable();
    }

    public function test_managers_cannot_run_payroll(): void
    {
        $this->actingAs($this->manager)->postJson('/admin/api/payroll/runs', ['period' => '2026-10'])->assertForbidden();
        $this->actingAs($this->manager)->getJson('/admin/api/payroll/runs')->assertForbidden();
    }

    public function test_attendance_correction_records_lateness_and_hours(): void
    {
        $employee = $this->employee();

        $this->actingAs($this->manager)->postJson('/admin/api/hr/attendance', [
            'employee_id' => $employee->id, 'work_date' => '2026-10-19', 'check_in' => '09:40', 'check_out' => '18:10',
        ])->assertOk()->assertJsonPath('data.late_minutes', 40)->assertJsonPath('data.hours', 8.5);

        $this->assertSame(1, AttendanceRecord::where('employee_id', $employee->id)->count());
    }

    public function test_departments_and_job_titles_are_separate_lists_that_can_be_managed_on_their_own(): void
    {
        $dept = $this->actingAs($this->manager)->postJson('/admin/api/hr/lists/departments', ['name' => 'Design'])->assertCreated()->json('data');
        $title = $this->actingAs($this->manager)->postJson('/admin/api/hr/lists/job-titles', ['name' => 'Product Designer', 'department_id' => $dept['id']])->assertCreated()->json('data');

        $employee = $this->actingAs($this->manager)->postJson('/admin/api/hr/employees', $this->payload(['department_id' => $dept['id'], 'job_title_id' => $title['id']]))
            ->assertCreated()->assertJsonPath('data.department', 'Design')->assertJsonPath('data.job_title', 'Product Designer')->json('data');

        // A list entry in use cannot be deleted, but it can be renamed; the employee follows the new name.
        $this->actingAs($this->manager)->deleteJson("/admin/api/hr/lists/departments/{$dept['id']}")->assertUnprocessable();
        $this->actingAs($this->manager)->putJson("/admin/api/hr/lists/departments/{$dept['id']}", ['name' => 'Product Design'])->assertOk();
        $this->actingAs($this->manager)->getJson("/admin/api/hr/employees/{$employee['id']}")->assertJsonPath('data.department', 'Product Design');

        // An unused entry can be deleted.
        $spare = $this->actingAs($this->manager)->postJson('/admin/api/hr/lists/job-titles', ['name' => 'Intern', 'department_id' => $dept['id']])->json('data');
        $this->actingAs($this->manager)->deleteJson("/admin/api/hr/lists/job-titles/{$spare['id']}")->assertNoContent();
    }

    public function test_an_employee_can_have_several_allowances_and_each_one_is_paid_on_the_slip(): void
    {
        $rent = $this->actingAs($this->manager)->postJson('/admin/api/hr/lists/allowance-types', ['name' => 'Housing'])->json('data');
        $fuel = $this->actingAs($this->manager)->postJson('/admin/api/hr/lists/allowance-types', ['name' => 'Mobile'])->json('data');

        $employee = $this->actingAs($this->manager)->postJson('/admin/api/hr/employees', $this->payload([
            'basic_salary' => '100000',
            'allowances' => [['allowance_type_id' => $rent['id'], 'amount' => '40000'], ['allowance_type_id' => $fuel['id'], 'amount' => '5000']],
        ]))->assertCreated()->json('data');

        $this->assertSame(2, Employee::find($employee['id'])->allowances()->count());

        $run = PayrollRun::create(['period' => '2026-10']);
        $slip = Payroll::generate($run)->slips->firstWhere('employee_id', $employee['id']);

        $this->assertSame(4500000, $slip->allowance_cents);
        $this->assertSame(14500000, $slip->gross_cents); // 100,000 + 40,000 + 5,000
        $this->assertSame(['Basic salary', 'Housing', 'Mobile'], $slip->items->pluck('label')->all());
    }

    public function test_the_same_allowance_cannot_be_listed_twice_for_one_person(): void
    {
        $rent = $this->actingAs($this->manager)->postJson('/admin/api/hr/lists/allowance-types', ['name' => 'Lunch'])->json('data');

        $this->actingAs($this->manager)->postJson('/admin/api/hr/employees', $this->payload([
            'allowances' => [['allowance_type_id' => $rent['id'], 'amount' => '1000'], ['allowance_type_id' => $rent['id'], 'amount' => '2000']],
        ]))->assertUnprocessable();
    }

    public function test_a_job_title_belongs_to_one_department_and_employees_must_match(): void
    {
        $design = $this->actingAs($this->manager)->postJson('/admin/api/hr/lists/departments', ['name' => 'Design Team'])->json('data');
        $engineering = $this->actingAs($this->manager)->postJson('/admin/api/hr/lists/departments', ['name' => 'Engineering Team'])->json('data');
        $this->actingAs($this->manager)->postJson('/admin/api/hr/lists/job-titles', ['name' => 'Lead'])->assertUnprocessable();

        $lead = $this->actingAs($this->manager)->postJson('/admin/api/hr/lists/job-titles', ['name' => 'Lead', 'department_id' => $design['id']])->assertCreated()->json('data');
        // The same title name is fine under a different department.
        $this->actingAs($this->manager)->postJson('/admin/api/hr/lists/job-titles', ['name' => 'Lead', 'department_id' => $engineering['id']])->assertCreated();

        $this->actingAs($this->manager)->postJson('/admin/api/hr/employees', $this->payload(['department_id' => $engineering['id'], 'job_title_id' => $lead['id']]))
            ->assertUnprocessable()->assertJsonValidationErrors('job_title_id');

        // Choosing only the title sets the department from it.
        $this->actingAs($this->manager)->postJson('/admin/api/hr/employees', $this->payload(['job_title_id' => $lead['id']]))
            ->assertCreated()->assertJsonPath('data.department', 'Design Team');

        // Departments that still have titles or people cannot be deleted.
        $this->actingAs($this->manager)->deleteJson("/admin/api/hr/lists/departments/{$design['id']}")->assertUnprocessable();
    }
}