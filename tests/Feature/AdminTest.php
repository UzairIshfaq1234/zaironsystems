<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Enquiry;
use App\Models\Role;
use App\Models\Subscriber;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\UserInvitation;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Correct-Horse-9';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
    }

    private function userWithRole(string $slug, array $attrs = []): User
    {
        $user = User::factory()->create(['password' => self::PASSWORD, ...$attrs]);
        $user->roles()->attach(Role::where('slug', $slug)->value('id'));

        return $user->fresh();
    }

    private function superAdmin(array $attrs = []): User
    {
        return $this->userWithRole('super-admin', $attrs);
    }

    /* ---------- Portal shell ---------- */

    public function test_the_portal_is_a_separate_noindexed_app(): void
    {
        $this->withoutVite();
        $this->get('/admin/login')->assertOk()
            ->assertSee('<div id="admin-root"></div>', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertDontSee('<div id="root"></div>', false);
        $this->get('/robots.txt')->assertSee('Disallow: /admin');
    }

    public function test_the_superadmin_command_creates_a_super_admin(): void
    {
        $this->artisan('zairon:superadmin', ['email' => 'Boss@Zairon.test', '--name' => 'Boss', '--password' => self::PASSWORD])->assertSuccessful();

        $boss = User::where('email', 'boss@zairon.test')->sole();
        $this->assertTrue($boss->isSuperAdmin());
        $this->assertSame(Role::catalog(), $boss->permissions());
    }

    public function test_the_superadmin_command_rejects_weak_passwords(): void
    {
        $this->artisan('zairon:superadmin', ['email' => 'boss@zairon.test', '--password' => 'password'])->assertFailed();
        $this->assertSame(0, User::count());
    }

    /* ---------- Authentication ---------- */

    public function test_admins_can_sign_in_and_out(): void
    {
        $admin = $this->superAdmin(['email' => 'admin@zairon.test']);

        $this->postJson('/admin/api/login', ['email' => 'admin@zairon.test', 'password' => self::PASSWORD])
            ->assertOk()
            ->assertJsonPath('user.is_super_admin', true)
            ->assertJsonStructure(['user' => ['permissions'], 'csrf']);
        $this->assertAuthenticatedAs($admin);
        $this->assertNotNull($admin->fresh()->last_login_at);

        $this->postJson('/admin/api/logout')->assertOk();
        $this->assertGuest();
    }

    public function test_wrong_passwords_and_disabled_accounts_get_the_same_error(): void
    {
        $this->superAdmin(['email' => 'admin@zairon.test']);
        $this->userWithRole('sales', ['email' => 'off@zairon.test', 'is_active' => false]);

        foreach ([['admin@zairon.test', 'nope'], ['off@zairon.test', self::PASSWORD], ['ghost@zairon.test', self::PASSWORD]] as [$email, $password]) {
            $this->postJson('/admin/api/login', compact('email', 'password'))
                ->assertUnprocessable()
                ->assertJsonPath('errors.email.0', 'These credentials do not match our records.');
        }
        $this->assertGuest();
        $this->assertSame(3, ActivityLog::where('action', 'auth.failed')->count());
    }

    public function test_sign_in_is_rate_limited(): void
    {
        $this->superAdmin(['email' => 'admin@zairon.test']);
        foreach (range(1, 5) as $i) {
            $this->postJson('/admin/api/login', ['email' => 'admin@zairon.test', 'password' => 'wrong'])->assertUnprocessable();
        }

        $this->postJson('/admin/api/login', ['email' => 'admin@zairon.test', 'password' => self::PASSWORD])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_api_requires_sign_in(): void
    {
        $this->getJson('/admin/api/me')->assertUnauthorized();
        $this->getJson('/admin/api/enquiries')->assertUnauthorized();
    }

    public function test_deactivated_users_are_signed_out_on_their_next_request(): void
    {
        $user = $this->userWithRole('sales');
        $this->actingAs($user)->getJson('/admin/api/me')->assertOk();

        $user->update(['is_active' => false]);
        $this->actingAs($user)->getJson('/admin/api/me')->assertUnauthorized();
    }

    public function test_forgot_password_never_reveals_whether_an_account_exists(): void
    {
        Notification::fake();
        $admin = $this->superAdmin(['email' => 'admin@zairon.test']);

        $known = $this->postJson('/admin/api/forgot-password', ['email' => 'admin@zairon.test'])->assertOk()->json('message');
        $unknown = $this->postJson('/admin/api/forgot-password', ['email' => 'nobody@zairon.test'])->assertOk()->json('message');

        $this->assertSame($known, $unknown);
        Notification::assertSentTo($admin, ResetPasswordNotification::class);
    }

    public function test_password_reset_flow(): void
    {
        Notification::fake();
        $admin = $this->superAdmin(['email' => 'admin@zairon.test']);
        $this->postJson('/admin/api/forgot-password', ['email' => 'admin@zairon.test']);

        $token = null;
        Notification::assertSentTo($admin, ResetPasswordNotification::class, function ($n) use (&$token, $admin) {
            $url = $n->toMail($admin)->viewData['url'];
            parse_str(parse_url($url, PHP_URL_QUERY), $q);
            $token = $q['token'];

            return str_contains($url, '/admin/reset-password?');
        });

        $this->postJson('/admin/api/reset-password', ['token' => $token, 'email' => 'admin@zairon.test', 'password' => 'weak', 'password_confirmation' => 'weak'])
            ->assertUnprocessable()->assertJsonValidationErrors('password');
        $this->postJson('/admin/api/reset-password', ['token' => $token, 'email' => 'admin@zairon.test', 'password' => 'Brand-New-Pass1', 'password_confirmation' => 'Brand-New-Pass1'])
            ->assertOk();
        $this->postJson('/admin/api/login', ['email' => 'admin@zairon.test', 'password' => 'Brand-New-Pass1'])->assertOk();
    }

    /* ---------- Permissions ---------- */

    public function test_routes_enforce_permissions(): void
    {
        $marketing = $this->userWithRole('marketing'); // dashboard + subscribers only
        $subscriber = Subscriber::create(['email' => 'keep@x.test']);

        $this->actingAs($marketing)->getJson('/admin/api/overview')->assertOk();
        $this->actingAs($marketing)->getJson('/admin/api/subscribers')->assertOk();
        $this->actingAs($marketing)->getJson('/admin/api/enquiries')->assertForbidden();
        $this->actingAs($marketing)->getJson('/admin/api/users')->assertForbidden();
        $this->actingAs($marketing)->getJson('/admin/api/roles')->assertForbidden();
        $this->actingAs($marketing)->getJson('/admin/api/activity')->assertForbidden();
        $this->actingAs($marketing)->deleteJson("/admin/api/subscribers/{$subscriber->id}")->assertForbidden();
        $this->assertModelExists($subscriber);
    }

    public function test_super_admin_passes_every_permission(): void
    {
        $admin = $this->superAdmin();
        foreach (['overview', 'enquiries', 'subscribers', 'users', 'roles', 'roles/permissions', 'activity'] as $path) {
            $this->actingAs($admin)->getJson("/admin/api/{$path}")->assertOk();
        }
    }

    public function test_super_admin_creates_roles_and_assigns_them(): void
    {
        $admin = $this->superAdmin();

        $role = $this->actingAs($admin)->postJson('/admin/api/roles', [
            'name' => 'Support',
            'description' => 'Answers enquiries',
            'permissions' => ['enquiries.view', 'enquiries.update'],
        ])->assertCreated()->assertJsonPath('data.slug', 'support')->json('data');

        $this->actingAs($admin)->postJson('/admin/api/roles', ['name' => 'Bad', 'permissions' => ['everything.forever']])
            ->assertUnprocessable()->assertJsonValidationErrors('permissions.0');

        $user = $this->userWithRole('marketing');
        $this->actingAs($admin)->patchJson("/admin/api/users/{$user->id}", ['roles' => [$role['id']]])->assertOk();

        $this->assertTrue($user->fresh()->hasPermission('enquiries.update'));
        $this->assertFalse($user->fresh()->hasPermission('subscribers.view'));
    }

    public function test_system_role_cannot_be_changed_or_deleted(): void
    {
        $admin = $this->superAdmin();
        $system = Role::where('slug', 'super-admin')->sole();

        $this->actingAs($admin)->putJson("/admin/api/roles/{$system->id}", ['name' => 'Hacked', 'permissions' => []])->assertForbidden();
        $this->actingAs($admin)->deleteJson("/admin/api/roles/{$system->id}")->assertForbidden();
    }

    /* ---------- Privilege escalation ---------- */

    public function test_non_super_admins_cannot_escalate_privileges(): void
    {
        $managerRole = Role::where('slug', 'manager')->sole();
        $managerRole->update(['permissions' => [...$managerRole->permissions, 'users.create', 'users.update', 'roles.view', 'roles.create', 'roles.update']]);
        $manager = $this->userWithRole('manager');
        $superRole = Role::where('slug', 'super-admin')->sole();
        $boss = $this->superAdmin();
        $peer = $this->userWithRole('sales');

        // Can't grant permissions they don't hold.
        $this->actingAs($manager)->postJson('/admin/api/roles', ['name' => 'Escalate', 'permissions' => ['roles.delete']])->assertForbidden();
        // Can't hand out the super admin role.
        $this->actingAs($manager)->postJson('/admin/api/users', ['name' => 'Mallory', 'email' => 'mallory@x.test', 'roles' => [$superRole->id]])->assertForbidden();
        $this->actingAs($manager)->patchJson("/admin/api/users/{$peer->id}", ['roles' => [$superRole->id]])->assertForbidden();
        // Can't touch a super admin.
        $this->actingAs($manager)->patchJson("/admin/api/users/{$boss->id}", ['is_active' => false])->assertForbidden();
        // Can't change their own roles.
        $this->actingAs($manager)->patchJson("/admin/api/users/{$manager->id}", ['roles' => [$managerRole->id]])->assertUnprocessable();

        $this->assertFalse($peer->fresh()->isSuperAdmin());
        $this->assertTrue($boss->fresh()->is_active);
    }

    public function test_the_last_super_admin_is_protected(): void
    {
        $admin = $this->superAdmin();
        $other = $this->superAdmin();
        $sales = Role::where('slug', 'sales')->value('id');

        $this->actingAs($admin)->deleteJson("/admin/api/users/{$admin->id}")->assertStatus(422); // not yourself
        $this->actingAs($admin)->patchJson("/admin/api/users/{$other->id}", ['roles' => [$sales]])->assertOk(); // two super admins: fine
        $this->actingAs($other->fresh())->getJson('/admin/api/users')->assertForbidden(); // demoted user lost access

        $this->actingAs($admin)->patchJson("/admin/api/users/{$admin->id}", ['is_active' => false])->assertUnprocessable();
        $this->assertTrue($admin->fresh()->isSuperAdmin());
    }

    /* ---------- Invitations ---------- */

    public function test_invited_users_get_an_email_and_set_their_own_password(): void
    {
        Notification::fake();
        $admin = $this->superAdmin();
        $sales = Role::where('slug', 'sales')->value('id');

        $this->actingAs($admin)->postJson('/admin/api/users', ['name' => 'Nadia', 'email' => 'Nadia@Zairon.test', 'roles' => [$sales]])
            ->assertCreated()->assertJsonPath('data.pending_invite', true);
        $nadia = User::where('email', 'nadia@zairon.test')->sole();

        $token = null;
        Notification::assertSentTo($nadia, UserInvitation::class, function ($n) use (&$token, $nadia) {
            parse_str(parse_url($n->toMail($nadia)->viewData['url'], PHP_URL_QUERY), $q);
            $token = $q['token'];

            return $q['invite'] === '1';
        });

        // An invitation token is not a password-reset token.
        $this->postJson('/admin/api/reset-password', ['token' => $token, 'email' => 'nadia@zairon.test', 'password' => 'Nadia-Pass-2026', 'password_confirmation' => 'Nadia-Pass-2026'])
            ->assertUnprocessable();
        $this->postJson('/admin/api/reset-password', ['token' => $token, 'email' => 'nadia@zairon.test', 'password' => 'Nadia-Pass-2026', 'password_confirmation' => 'Nadia-Pass-2026', 'invite' => true])
            ->assertOk();

        $this->postJson('/admin/api/login', ['email' => 'nadia@zairon.test', 'password' => 'Nadia-Pass-2026'])
            ->assertOk()->assertJsonPath('user.permissions', ['dashboard.view', 'enquiries.view', 'enquiries.update']);
    }

    /* ---------- Data modules ---------- */

    public function test_enquiry_management(): void
    {
        Storage::fake('local');
        $sales = $this->userWithRole('sales');
        $marketing = $this->userWithRole('marketing');
        $path = UploadedFile::fake()->create('brief.pdf', 10, 'application/pdf')->store('briefs');
        $enquiry = Enquiry::create(['name' => 'Ada', 'email' => 'ada@x.test', 'company' => 'AE', 'details' => 'Build it please', 'brief_path' => $path, 'brief_name' => 'brief.pdf']);

        $this->actingAs($sales)->getJson('/admin/api/enquiries?q=ada')->assertOk()->assertJsonPath('meta.total', 1)->assertJsonMissingPath('data.0.brief_path');
        $this->actingAs($sales)->patchJson("/admin/api/enquiries/{$enquiry->id}", ['status' => 'won', 'notes' => 'Signed!'])->assertOk()->assertJsonPath('data.status', 'won');
        $this->actingAs($sales)->patchJson("/admin/api/enquiries/{$enquiry->id}", ['status' => 'hacked'])->assertUnprocessable();
        $this->actingAs($sales)->get("/admin/api/enquiries/{$enquiry->id}/brief")->assertOk()->assertDownload('brief.pdf');
        $this->actingAs($marketing)->get("/admin/api/enquiries/{$enquiry->id}/brief")->assertForbidden();
        $this->actingAs($sales)->deleteJson("/admin/api/enquiries/{$enquiry->id}")->assertForbidden(); // sales can't delete

        $this->assertSame(1, ActivityLog::where('action', 'enquiry.status')->count());
    }

    public function test_subscriber_export_neutralises_spreadsheet_formulas(): void
    {
        Subscriber::create(['email' => '=cmd|calc@x.test']);
        Subscriber::create(['email' => 'ok@x.test']);
        $admin = $this->superAdmin();

        $csv = $this->actingAs($admin)->get('/admin/api/subscribers/export')->assertOk()->streamedContent();

        $this->assertStringContainsString("'=cmd|calc@x.test", $csv);
        $this->assertStringContainsString('ok@x.test', $csv);
    }

    public function test_admin_responses_are_never_cached(): void
    {
        $this->actingAs($this->superAdmin())->getJson('/admin/api/overview')
            ->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Cache-Control', 'no-store, private');
    }
}
