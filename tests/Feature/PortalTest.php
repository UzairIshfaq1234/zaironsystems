<?php

namespace Tests\Feature;

use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\PerformanceReview;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Payroll;
use Carbon\Carbon;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The employee portal: its own sign-in, and every record is the signed-in employee's own. */
class PortalTest extends TestCase
{
    use RefreshDatabase;

    private Employee $ayesha;

    private Employee $omar;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(RoleSeeder::class);
        Setting::put('accounting', ['base_currency' => 'PKR', 'fiscal_year_start' => 7, 'lock_date' => null]);

        $this->ayesha = $this->employee('ayesha@example.com', 'Ayesha Khan');
        $this->omar = $this->employee('omar@example.com', 'Omar Farooq');
    }

    private function employee(string $email, string $name): Employee
    {
        $employee = Employee::create([
            'code' => Employee::nextCode(), 'name' => $name, 'email' => $email, 'joined_on' => '2026-01-05',
            'basic_salary_cents' => 10000000, 'shift_start' => '09:00:00', 'shift_end' => '18:00:00',
        ]);
        // The password is never mass-assigned; set it the way the password reset does.
        $employee->forceFill(['password' => 'Portal-Pass2026'])->save();

        return $employee;
    }

    private function signIn(Employee $employee)
    {
        return $this->actingAs($employee, 'employee');
    }

    public function test_employees_sign_in_with_the_portal_and_wrong_details_get_one_message(): void
    {
        $this->postJson('/portal/api/login', ['email' => 'ayesha@example.com', 'password' => 'Portal-Pass2026'])
            ->assertOk()->assertJsonPath('employee.code', $this->ayesha->code);

        $this->postJson('/portal/api/login', ['email' => 'ayesha@example.com', 'password' => 'wrong'])
            ->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson('/portal/api/login', ['email' => 'nobody@example.com', 'password' => 'wrong'])
            ->assertJsonValidationErrors('email');
    }

    public function test_a_closed_employee_cannot_sign_in(): void
    {
        $this->ayesha->update(['status' => 'left']);

        $this->postJson('/portal/api/login', ['email' => 'ayesha@example.com', 'password' => 'Portal-Pass2026'])->assertUnprocessable();
    }

    public function test_an_employee_cannot_use_the_admin_api(): void
    {
        $this->postJson('/portal/api/login', ['email' => 'ayesha@example.com', 'password' => 'Portal-Pass2026'])->assertOk();

        $this->getJson('/portal/api/home')->assertOk();
        $this->getJson('/admin/api/hr/employees')->assertUnauthorized();
    }

    public function test_an_admin_cannot_use_the_portal_api(): void
    {
        $admin = User::factory()->create();
        $admin->roles()->attach(Role::where('slug', 'manager')->value('id'));

        $this->actingAs($admin)->getJson('/portal/api/home')->assertUnauthorized();
    }
    public function test_employees_only_see_their_own_records(): void
    {
        $theirs = PerformanceReview::create(['employee_id' => $this->omar->id, 'period' => '2026-Q3', 'score' => 4]);
        $mine = PerformanceReview::create(['employee_id' => $this->ayesha->id, 'period' => '2026-Q3', 'score' => 5]);

        $this->signIn($this->ayesha)->getJson('/portal/api/reviews')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $mine->id);

        // Changing the id in the address does not reach someone else's review.
        $this->signIn($this->ayesha)->postJson("/portal/api/reviews/{$theirs->id}/reply", ['comment' => 'hello'])->assertNotFound();
    }

    public function test_check_in_once_a_day_and_check_out_after_it(): void
    {
        $this->signIn($this->ayesha)->postJson('/portal/api/attendance/check-out')->assertUnprocessable();

        $this->signIn($this->ayesha)->postJson('/portal/api/attendance/check-in')->assertCreated();
        $this->signIn($this->ayesha)->postJson('/portal/api/attendance/check-in')->assertUnprocessable();

        $this->signIn($this->ayesha)->postJson('/portal/api/attendance/check-out')->assertOk();
        $this->signIn($this->ayesha)->postJson('/portal/api/attendance/check-out')->assertUnprocessable();

        $this->assertSame(1, AttendanceRecord::where('employee_id', $this->ayesha->id)->count());
    }

    public function test_checking_in_late_is_reported_back(): void
    {
        // 09:40 Pakistan time (04:40 UTC): 40 minutes after the 09:00 shift start.
        $this->travelTo(Carbon::parse('2026-10-19 04:40:00', 'UTC'));

        $this->signIn($this->ayesha)->postJson('/portal/api/attendance/check-in')
            ->assertCreated()->assertJsonPath('data.late_minutes', 40)->assertJsonFragment(['message' => 'Checked in. You are 40 minutes late.']);
    }

    public function test_leave_requests_respect_the_balance_and_do_not_overlap(): void
    {
        $tomorrow = now()->addDays(3)->toDateString();
        $dayAfter = now()->addDays(5)->toDateString();

        $this->signIn($this->ayesha)->postJson('/portal/api/leave', [
            'type' => 'annual', 'start_date' => $tomorrow, 'end_date' => $dayAfter, 'reason' => 'Family trip',
        ])->assertCreated()->assertJsonPath('data.status', 'pending')->assertJsonPath('data.days', 3);

        // Overlapping dates are refused.
        $this->signIn($this->ayesha)->postJson('/portal/api/leave', ['type' => 'casual', 'start_date' => $dayAfter, 'end_date' => $dayAfter])
            ->assertUnprocessable()->assertJsonValidationErrors('start_date');

        // Sick leave is only 8 days a year.
        $this->signIn($this->ayesha)->postJson('/portal/api/leave', ['type' => 'sick', 'start_date' => now()->addDays(20)->toDateString(), 'end_date' => now()->addDays(40)->toDateString()])
            ->assertUnprocessable()->assertJsonValidationErrors('type');

        // Only pending requests can be cancelled.
        $leave = $this->ayesha->leaveRequests()->first();
        $this->signIn($this->ayesha)->postJson("/portal/api/leave/{$leave->id}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    }

    public function test_draft_payroll_slips_are_hidden_until_posted(): void
    {
        $run = PayrollRun::create(['period' => '2026-10']);
        $slip = Payroll::generate($run)->slips->first();

        $this->signIn($this->ayesha)->getJson('/portal/api/slips')->assertOk()->assertJsonCount(0, 'data');
        $this->signIn($this->ayesha)->get("/portal/api/slips/{$slip->id}/pdf")->assertNotFound();

        $run->forceFill(['status' => 'posted', 'posted_at' => now()])->save();
        $this->signIn($this->ayesha)->getJson('/portal/api/slips')->assertJsonCount(1, 'data');
        $this->signIn($this->ayesha)->get("/portal/api/slips/{$slip->id}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');

        // Someone else's slip is not found, even when posted.
        $otherSlip = $run->slips()->where('employee_id', $this->omar->id)->first();
        $this->signIn($this->ayesha)->get("/portal/api/slips/{$otherSlip->id}/pdf")->assertNotFound();
    }

    public function test_reviewing_a_review_records_the_reply_and_acknowledgement(): void
    {
        $review = PerformanceReview::create(['employee_id' => $this->ayesha->id, 'period' => '2026-Q3', 'score' => 4, 'strengths' => 'Clear communication']);

        $this->signIn($this->ayesha)->postJson("/portal/api/reviews/{$review->id}/reply", ['comment' => 'Thanks, I will work on the goals.'])
            ->assertOk()->assertJsonPath('data.acknowledged', true)->assertJsonPath('data.employee_comment', 'Thanks, I will work on the goals.');
    }

    public function test_password_can_be_changed_with_the_current_one(): void
    {
        $this->signIn($this->ayesha)->putJson('/portal/api/password', ['current_password' => 'wrong', 'password' => 'New-Portal2026', 'password_confirmation' => 'New-Portal2026'])
            ->assertUnprocessable()->assertJsonValidationErrors('current_password');

        $this->signIn($this->ayesha)->putJson('/portal/api/password', ['current_password' => 'Portal-Pass2026', 'password' => 'New-Portal2026', 'password_confirmation' => 'New-Portal2026'])
            ->assertOk();
        $this->assertTrue(Hash::check('New-Portal2026', $this->ayesha->fresh()->password));
    }

    public function test_the_home_screen_shows_todays_shift_and_check_in_state(): void
    {
        $this->signIn($this->ayesha)->getJson('/portal/api/home')
            ->assertOk()->assertJsonPath('shift.start', '09:00')->assertJsonPath('can_check_in', true)->assertJsonPath('can_check_out', false);
    }
}
