<?php

namespace Tests\Feature;

use App\Mail\EnquiryConfirmation;
use App\Mail\EnquiryReceived;
use App\Models\Enquiry;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EnquiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
    }

    private function valid(array $overrides = []): array
    {
        return [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'company' => 'Analytical Engines',
            'phone' => '+92 300 1234567',
            'services' => ['ai', 'automation'],
            'budget' => '$15k – $50k',
            'details' => 'We want to automate invoice processing with computer vision.',
            'consent' => '1',
            'website' => '',
            ...$overrides,
        ];
    }

    public function test_a_valid_enquiry_is_stored_and_emailed(): void
    {
        config(['zairon.inbox' => 'sales@zairon.test']);

        $this->postJson('/api/contact', $this->valid())->assertCreated()->assertJsonStructure(['message']);

        $enquiry = Enquiry::sole();
        $this->assertSame('Ada Lovelace', $enquiry->name);
        $this->assertSame(['ai', 'automation'], $enquiry->services);
        $this->assertNotNull($enquiry->ip_address);
        Mail::assertQueued(EnquiryReceived::class, fn ($mail) => $mail->hasTo('sales@zairon.test') && $mail->hasReplyTo('ada@example.com'));
    }

    public function test_service_page_forms_record_the_service_and_chosen_plan(): void
    {
        $this->postJson('/api/contact', $this->valid(['service' => 'cyber-security', 'plan' => 'discovery', 'services' => ['security']]))
            ->assertCreated();

        $enquiry = Enquiry::sole();
        $this->assertSame('cyber-security', $enquiry->service);
        $this->assertSame('discovery', $enquiry->plan);
    }

    public function test_unknown_services_and_plans_are_rejected(): void
    {
        $this->postJson('/api/contact', $this->valid(['service' => '../../etc/passwd', 'plan' => 'free-forever']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['service', 'plan']);
    }

    public function test_the_visitor_always_gets_a_confirmation_email(): void
    {
        config(['zairon.inbox' => null]);

        $this->postJson('/api/contact', $this->valid())->assertCreated();

        Mail::assertQueued(EnquiryConfirmation::class, fn ($mail) => $mail->hasTo('ada@example.com'));
        Mail::assertNotQueued(EnquiryReceived::class); // no inbox and no admins yet
    }

    public function test_without_an_inbox_the_team_email_goes_to_admins_who_can_see_enquiries(): void
    {
        config(['zairon.inbox' => null]);
        $this->seed(RoleSeeder::class);
        $sales = User::factory()->create(['email' => 'sales@zairon.test']);
        $sales->roles()->attach(Role::where('slug', 'sales')->value('id'));
        $marketing = User::factory()->create(['email' => 'marketing@zairon.test']);
        $marketing->roles()->attach(Role::where('slug', 'marketing')->value('id'));

        $this->postJson('/api/contact', $this->valid())->assertCreated();

        Mail::assertQueued(EnquiryReceived::class, fn ($mail) => $mail->hasTo('sales@zairon.test') && ! $mail->hasTo('marketing@zairon.test'));
    }

    public function test_emails_render_and_escape_visitor_input(): void
    {
        $this->postJson('/api/contact', $this->valid(['name' => '<b>Eve</b>', 'details' => 'Click [here](https://evil.example) <script>x</script>']))->assertCreated();
        $enquiry = Enquiry::sole();

        $team = (new EnquiryReceived($enquiry))->render();
        $this->assertStringNotContainsString('<script>x</script>', $team);
        $this->assertStringNotContainsString('href="https://evil.example"', $team);
        $this->assertStringContainsString('&lt;b&gt;Eve&lt;/b&gt;', $team);

        $visitor = (new EnquiryConfirmation($enquiry))->render();
        $this->assertStringNotContainsString('evil.example', $visitor); // the confirmation never echoes the message
    }

    public function test_attachments_are_stored_privately_under_a_random_name(): void
    {
        $file = UploadedFile::fake()->create('brief.pdf', 200, 'application/pdf');

        $this->post('/api/contact', $this->valid(['brief' => $file]), ['Accept' => 'application/json'])->assertCreated();

        $enquiry = Enquiry::sole();
        $this->assertSame('brief.pdf', $enquiry->brief_name);
        $this->assertStringStartsWith('briefs/', $enquiry->brief_path);
        $this->assertStringNotContainsString('brief.pdf', $enquiry->brief_path);
        Storage::disk('local')->assertExists($enquiry->brief_path);
    }

    public function test_dangerous_or_oversized_files_are_rejected(): void
    {
        $this->post('/api/contact', $this->valid(['brief' => UploadedFile::fake()->create('shell.php', 1, 'application/x-php')]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('brief');

        $this->post('/api/contact', $this->valid(['brief' => UploadedFile::fake()->create('big.pdf', 11 * 1024, 'application/pdf')]), ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('brief');

        $this->assertSame(0, Enquiry::count());
    }

    public function test_required_fields_are_validated_with_friendly_messages(): void
    {
        $this->postJson('/api/contact', ['website' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'company', 'details', 'consent'])
            ->assertJsonPath('errors.consent.0', 'Please accept the privacy policy.');
    }

    public function test_only_known_services_budgets_and_sane_values_are_accepted(): void
    {
        $this->postJson('/api/contact', $this->valid([
            'services' => ['ai', '<script>alert(1)</script>'],
            'budget' => 'a million',
            'email' => 'not-an-email',
            'phone' => 'call me maybe',
            'details' => str_repeat('a', 5001),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['services.1', 'budget', 'email', 'phone', 'details']);
    }

    public function test_honeypot_submissions_look_successful_but_are_dropped(): void
    {
        config(['zairon.inbox' => 'sales@zairon.test']);

        $this->postJson('/api/contact', $this->valid(['website' => 'https://spam.example']))->assertOk();

        $this->assertSame(0, Enquiry::count());
        Mail::assertNothingQueued(); // bots get neither the team email nor a confirmation
    }

    public function test_submissions_are_rate_limited(): void
    {
        foreach (range(1, 5) as $i) {
            $this->postJson('/api/contact', $this->valid())->assertCreated();
        }

        $this->postJson('/api/contact', $this->valid())->assertTooManyRequests();
        $this->assertSame(5, Enquiry::count());
    }
}
