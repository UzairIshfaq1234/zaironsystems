<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Invoice;
use App\Models\Role;
use App\Models\User;
use App\Notifications\PortalAlert;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PortalSearchTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(string $roleSlug): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('slug', $roleSlug)->value('id'));

        return $user;
    }

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seed(RoleSeeder::class);
    }

    public function test_search_only_returns_the_areas_the_user_can_open(): void
    {
        $client = Client::create(['name' => 'Ada Nova', 'company' => 'Nova Retail', 'email' => 'ada@nova.test']);
        $invoice = (new Invoice(['status' => 'draft', 'issue_date' => now(), 'due_date' => now(), 'currency' => 'USD', 'tax_rate' => 0, 'client_id' => $client->id]))
            ->saveWithItems(['client_id' => $client->id, 'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString(), 'currency' => 'USD', 'tax_rate' => 0, 'discount' => 0, 'items' => [['description' => 'Build', 'quantity' => 1, 'unit_price' => '100']]]);

        $accountant = $this->userWith('accountant');
        $groups = collect($this->actingAs($accountant)->getJson('/admin/api/search?q=nova')->assertOk()->json('groups'))->pluck('key');
        $this->assertTrue($groups->contains('invoices'));
        $this->assertTrue($groups->contains('clients'));

        // Sales can see enquiries but not billing, so billing results must not leak through search.
        $sales = $this->userWith('sales');
        $salesGroups = collect($this->actingAs($sales)->getJson('/admin/api/search?q=nova')->assertOk()->json('groups'))->pluck('key');
        $this->assertFalse($salesGroups->contains('invoices'));
        $this->assertFalse($salesGroups->contains('clients'));

        // Searching by document number finds the invoice itself.
        $byNumber = $this->actingAs($accountant)->getJson('/admin/api/search?q='.$invoice->number)->json('groups');
        $this->assertSame($invoice->number, collect($byNumber)->firstWhere('key', 'invoices')['items'][0]['title']);
    }

    public function test_a_new_enquiry_notifies_only_people_who_can_view_enquiries(): void
    {
        $sales = $this->userWith('sales');
        $marketing = $this->userWith('marketing');

        $this->postJson('/api/contact', ['name' => 'Ada', 'email' => 'ada@example.com', 'company' => 'Analytical Engines', 'services' => ['ai'], 'budget' => '$15k – $50k', 'details' => 'We want to automate invoices.', 'consent' => '1', 'website' => ''])
            ->assertCreated();

        $this->assertSame(1, $sales->unreadNotifications()->count());
        $this->assertSame(0, $marketing->unreadNotifications()->count());
        $this->assertSame('enquiry', $sales->notifications()->first()->data['kind']);
    }

    public function test_notifications_belong_to_their_user_and_can_be_read(): void
    {
        $owner = $this->userWith('accountant');
        $other = $this->userWith('accountant');
        $owner->notify(new PortalAlert('payment', 'Money in', 'PKR 100 received', '/billing/invoices/1'));
        $notification = $owner->notifications()->first();

        $this->actingAs($owner)->getJson('/admin/api/notifications')
            ->assertOk()->assertJsonPath('unread', 1)->assertJsonPath('data.0.title', 'Money in')->assertJsonPath('data.0.url', '/billing/invoices/1');

        // Someone else's notification id does not exist for them.
        $this->actingAs($other)->postJson("/admin/api/notifications/{$notification->id}/read")->assertNotFound();

        $this->actingAs($owner)->postJson("/admin/api/notifications/{$notification->id}/read")->assertOk()->assertJsonPath('unread', 0);
        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_search_needs_at_least_two_characters(): void
    {
        $this->actingAs($this->userWith('accountant'))->getJson('/admin/api/search?q=a')->assertUnprocessable()->assertJsonValidationErrors('q');
    }
}
