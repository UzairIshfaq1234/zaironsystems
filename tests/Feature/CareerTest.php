<?php

namespace Tests\Feature;

use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CareerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
    }

    /** @return array<string, mixed> */
    private function validApplication(array $overrides = []): array
    {
        return [
            'job_opening_id' => JobOpening::factory()->create()->id,
            'name' => 'Grace Hopper',
            'email' => 'grace@example.com',
            'phone' => '+92 300 1234567',
            'links' => 'https://github.com/grace',
            'message' => 'I would love to help build compilers.',
            'consent' => '1',
            'website' => '',
            'cv' => UploadedFile::fake()->create('grace-cv.pdf', 200, 'application/pdf'),
            ...$overrides,
        ];
    }

    private function userWithPermissions(array $permissions): User
    {
        $role = Role::create(['name' => 'Recruiter', 'slug' => 'recruiter-'.uniqid(), 'permissions' => $permissions]);
        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user->fresh();
    }

    public function test_the_careers_page_lists_only_open_roles_in_order(): void
    {
        JobOpening::factory()->create(['title' => 'Second', 'sort_order' => 2]);
        JobOpening::factory()->create(['title' => 'First', 'sort_order' => 1]);
        JobOpening::factory()->closed()->create(['title' => 'Filled', 'sort_order' => 0]);

        $this->getJson('/api/careers/openings')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'First')
            ->assertJsonPath('data.1.title', 'Second')
            ->assertJsonCount(2, 'data');
    }

    public function test_a_valid_application_is_stored_with_its_cv_on_the_private_disk(): void
    {
        $this->postJson('/api/careers/apply', $this->validApplication())->assertCreated()->assertJsonStructure(['message']);

        $application = JobApplication::sole();
        $this->assertSame('Grace Hopper', $application->name);
        $this->assertSame('new', $application->status);
        $this->assertSame('grace-cv.pdf', $application->cv_name);
        $this->assertNotNull($application->ip_address);
        Storage::disk('local')->assertExists($application->cv_path);
    }

    public function test_applications_for_closed_roles_are_rejected(): void
    {
        $closed = JobOpening::factory()->closed()->create();

        $this->postJson('/api/careers/apply', $this->validApplication(['job_opening_id' => $closed->id]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['job_opening_id']);

        $this->assertDatabaseCount('job_applications', 0);
    }

    public function test_only_pdf_doc_and_docx_cvs_are_accepted(): void
    {
        $this->postJson('/api/careers/apply', $this->validApplication([
            'cv' => UploadedFile::fake()->create('cv.exe', 100, 'application/octet-stream'),
        ]))->assertUnprocessable()->assertJsonValidationErrors(['cv']);

        $this->assertDatabaseCount('job_applications', 0);
    }

    public function test_bot_submissions_are_dropped_without_storing_anything(): void
    {
        $this->postJson('/api/careers/apply', $this->validApplication(['website' => 'spam']))->assertOk();

        $this->assertDatabaseCount('job_applications', 0);
    }

    public function test_a_reviewer_can_read_applications_but_not_change_openings_or_status(): void
    {
        $application = JobApplication::factory()->create();
        $reviewer = $this->userWithPermissions(['careers.view']);

        $this->actingAs($reviewer)->getJson('/admin/api/careers/applications')->assertOk();
        $this->actingAs($reviewer)->getJson("/admin/api/careers/applications/{$application->id}/cv")->assertNotFound();
        $this->actingAs($reviewer)->postJson('/admin/api/careers/openings', [
            'title' => 'Designer', 'team' => 'Design', 'type' => 'Full-time', 'location' => 'Remote',
        ])->assertForbidden();
        $this->actingAs($reviewer)->patchJson("/admin/api/careers/applications/{$application->id}", ['status' => 'hired'])->assertForbidden();
    }

    public function test_a_manager_publishes_a_role_and_closing_it_hides_it_from_the_site(): void
    {
        $manager = $this->userWithPermissions(['careers.view', 'careers.manage']);

        $id = $this->actingAs($manager)->postJson('/admin/api/careers/openings', [
            'title' => 'Designer', 'team' => 'Design', 'type' => 'Full-time', 'location' => 'Remote',
        ])->assertCreated()->json('data.id');

        $this->getJson('/api/careers/openings')->assertJsonCount(1, 'data');

        $this->actingAs($manager)->putJson("/admin/api/careers/openings/{$id}", ['status' => 'closed'])->assertOk();

        $this->getJson('/api/careers/openings')->assertJsonCount(0, 'data');
    }

    public function test_deleting_an_application_removes_its_cv_file(): void
    {
        $this->postJson('/api/careers/apply', $this->validApplication())->assertCreated();
        $application = JobApplication::sole();
        $deleter = $this->userWithPermissions(['careers.view', 'careers.delete']);

        $this->actingAs($deleter)->deleteJson("/admin/api/careers/applications/{$application->id}")->assertNoContent();

        $this->assertDatabaseCount('job_applications', 0);
        Storage::disk('local')->assertMissing($application->cv_path);
    }
}
