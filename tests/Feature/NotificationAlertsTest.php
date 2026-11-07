<?php

namespace Tests\Feature;

use App\Models\Employee;
use App\Models\PayrollRun;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PortalAlert;
use App\Support\Payroll;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** Each action that matters reaches the right bell: the team for requests, the employee for answers. */
class NotificationAlertsTest extends TestCase
{
    use RefreshDatabase;

    private Employee $ayesha;

    private User $manager;

    private User $accountant;

    private User $sales;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(RoleSeeder::class);
        Setting::put('accounting', ['base_currency' => 'PKR', 'fiscal_year_start' => 7, 'lock_date' => null]);

        $this->manager = $this->userWith('manager');
        $this->accountant = $this->userWith('accountant');
        $this->sales = $this->userWith('sales');
        $this->ayesha = $this->makeEmployee('Ayesha Khan', 'ayesha@example.com');
    }

    private function userWith(string $roleSlug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $roleSlug)->value('id'));

        return $user;
    }

    private function makeEmployee(string $name, string $email): Employee
    {
        $employee = Employee::create([
            'code' => Employee::nextCode(), 'name' => $name, 'email' => $email, 'joined_on' => '2026-01-05',
            'basic_salary_cents' => 10000000, 'shift_start' => '09:00:00', 'shift_end' => '18:00:00',
        ]);
        $employee->forceFill(['password' => 'Portal-Pass2026'])->save();

        return $employee;
    }

    public function test_a_leave_request_alerts_hr_and_nobody_else(): void
    {
        $this->actingAs($this->ayesha, 'employee')->postJson('/portal/api/leave', [
            'type' => 'annual',
            'start_date' => now()->addDays(7)->toDateString(),
            'end_date' => now()->addDays(8)->toDateString(),
            'reason' => 'Family trip',
        ])->assertCreated();

        $alert = $this->manager->notifications()->sole();
        $this->assertSame('leave', $alert->data['kind']);
        $this->assertStringContainsString('Ayesha Khan', $alert->data['title']);
        $this->assertStringContainsString('Family trip', $alert->data['body']);
        $this->assertSame('/leave', $alert->data['url']);

        $this->assertSame(0, $this->sales->notifications()->count());
        $this->assertSame(0, $this->ayesha->notifications()->count());
    }

    public function test_a_leave_decision_alerts_the_employee_by_bell_and_email(): void
    {
        $leave = $this->ayesha->leaveRequests()->create([
            'type' => 'annual', 'start_date' => now()->addDays(7)->toDateString(), 'end_date' => now()->addDays(8)->toDateString(), 'days' => 2, 'status' => 'pending',
        ]);

        $this->actingAs($this->manager)->postJson("/admin/api/hr/leave/{$leave->id}/decide", ['decision' => 'approved', 'note' => 'Enjoy'])->assertOk();

        $alert = $this->ayesha->notifications()->sole();
        $this->assertSame('Your leave was approved', $alert->data['title']);
        $this->assertSame('/leave', $alert->data['url']);
        $this->assertStringContainsString('Enjoy', $alert->data['body']);
        // Employees also get an email; team members never do.
        $this->assertContains('mail', (new PortalAlert('leave', 'x', 'y', '/leave'))->via($this->ayesha));
        $this->assertSame(['database'], (new PortalAlert('leave', 'x', 'y', '/leave'))->via($this->manager));

        $this->assertSame(0, $this->manager->notifications()->count());
    }

    public function test_a_new_review_alerts_the_employee(): void
    {
        $this->actingAs($this->manager)->postJson('/admin/api/hr/reviews', [
            'employee_id' => $this->ayesha->id, 'period' => '2026-Q4', 'score' => 4, 'strengths' => 'Quick learner',
        ])->assertCreated();

        $alert = $this->ayesha->notifications()->sole();
        $this->assertSame('review', $alert->data['kind']);
        $this->assertSame('/reviews', $alert->data['url']);
        $this->assertSame(0, $this->sales->notifications()->count());
    }

    public function test_posting_payroll_alerts_each_employee_with_a_slip(): void
    {
        $omar = $this->makeEmployee('Omar Farooq', 'omar@example.com');
        $run = PayrollRun::create(['period' => '2026-10']);
        Payroll::generate($run);

        $this->actingAs($this->accountant)->postJson("/admin/api/payroll/runs/{$run->id}/post")->assertOk();

        foreach ([$this->ayesha, $omar] as $employee) {
            $alert = $employee->notifications()->sole();
            $this->assertSame('payslip', $alert->data['kind']);
            $this->assertSame('/payslips', $alert->data['url']);
        }
    }

    public function test_employees_only_see_and_clear_their_own_alerts(): void
    {
        $omar = $this->makeEmployee('Omar Farooq', 'omar@example.com');
        $this->ayesha->notify(new PortalAlert('review', 'New review', 'Read it in the portal.', '/reviews'));
        $id = $this->ayesha->notifications()->first()->id;

        $this->actingAs($omar, 'employee')->getJson('/portal/api/notifications')
            ->assertOk()->assertJsonPath('unread', 0)->assertJsonCount(0, 'data');
        $this->actingAs($omar, 'employee')->postJson("/portal/api/notifications/{$id}/read")->assertNotFound();

        $this->actingAs($this->ayesha, 'employee')->getJson('/portal/api/notifications')
            ->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('data.0.title', 'New review')->assertJsonPath('data.0.url', '/reviews');
        $this->actingAs($this->ayesha, 'employee')->postJson("/portal/api/notifications/{$id}/read")->assertOk()->assertJsonPath('unread', 0);

        $this->ayesha->notify(new PortalAlert('payslip', 'Slip ready', 'Download it.', '/payslips'));
        $this->actingAs($this->ayesha, 'employee')->postJson('/portal/api/notifications/read-all')->assertOk()->assertJsonPath('unread', 0);
    }

    public function test_employees_cannot_read_the_admin_bell(): void
    {
        $this->actingAs($this->ayesha, 'employee')->getJson('/admin/api/notifications')->assertUnauthorized();
    }

    public function test_team_members_cannot_read_the_portal_bell(): void
    {
        $this->actingAs($this->manager)->getJson('/portal/api/notifications')->assertUnauthorized();
    }
}
