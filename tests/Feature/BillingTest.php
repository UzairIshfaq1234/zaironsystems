<?php

namespace Tests\Feature;

use App\Mail\DocumentMail;
use App\Mail\QuoteResponded;
use App\Models\Client;
use App\Models\Enquiry;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\Role;
use App\Models\Setting;
use App\Models\User;
use App\Support\Billing;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BillingTest extends TestCase
{
    use RefreshDatabase;

    private User $accountant;

    private Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        $this->seed(RoleSeeder::class);
        Setting::put('accounting', ['base_currency' => 'USD', 'fiscal_year_start' => 7, 'lock_date' => null]);
        $this->accountant = User::factory()->create();
        $this->accountant->roles()->attach(Role::where('slug', 'accountant')->value('id'));
        $this->client = Client::create(['name' => 'Ada', 'company' => 'Analytical Engines', 'email' => 'ada@client.test']);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'client_id' => $this->client->id,
            'issue_date' => now()->toDateString(),
            'currency' => 'USD',
            'tax_rate' => 10,
            'discount' => '100',
            'items' => [
                ['description' => 'Design & build', 'quantity' => 2, 'unit_price' => '1250.50'],
                ['description' => 'Hosting setup', 'quantity' => '0.5', 'unit_price' => 199.99],
            ],
            ...$overrides,
        ];
    }

    private function quote(array $overrides = []): array
    {
        return $this->actingAs($this->accountant)->postJson('/admin/api/quotes', $this->payload(['valid_until' => now()->addMonth()->toDateString(), ...$overrides]))
            ->assertCreated()->json('data');
    }

    private function invoice(array $overrides = []): array
    {
        return $this->actingAs($this->accountant)->postJson('/admin/api/invoices', $this->payload(['due_date' => now()->addDays(14)->toDateString(), ...$overrides]))
            ->assertCreated()->json('data');
    }

    public function test_totals_are_exact_to_the_cent(): void
    {
        $q = $this->quote();

        // 2 × 1250.50 + 0.5 × 199.99 = 2501.00 + 100.00 (99.995 rounds to 100.00) = 2601.00
        $this->assertEquals(2601, $q['subtotal']);
        $this->assertEquals(100, $q['discount']);
        $this->assertEquals(250.1, $q['tax']);           // 10% of (2601.00 − 100.00)
        $this->assertEquals(2751.1, $q['total']);
        $this->assertSame(0, Billing::totals([], 5000, 10)['total']); // a discount never makes a total negative
    }

    public function test_quotes_and_invoices_are_numbered_from_001_in_their_own_sequence(): void
    {
        $this->assertSame('Q-ZAIRON-001', $this->quote()['number']);
        $this->assertSame('Q-ZAIRON-002', $this->quote()['number']);
        $this->assertSame('ZRON-001', $this->invoice()['number']); // invoices count separately
        $this->assertSame('ZRON-002', $this->invoice()['number']);
    }

    public function test_numbering_ignores_older_documents_with_other_formats(): void
    {
        Quote::query()->update(['number' => 'Q-2026-0001']); // an older-style number

        $this->assertSame('Q-ZAIRON-001', $this->quote()['number']);
    }

    public function test_a_quotation_stays_on_one_page_with_terms_and_notes(): void
    {
        $items = collect(range(1, 40))->map(fn ($i) => ['description' => "Module {$i}: product catalogue, search and checkout\nDesign, build and QA.", 'quantity' => 1, 'unit_price' => '880']);
        $quote = $this->quote(['items' => $items->all(), 'notes' => 'Kick-off within one week.']);

        $pdf = $this->actingAs($this->accountant)->get("/admin/api/quotes/{$quote['id']}/pdf")->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('#/Type\s*/Page\b#', $pdf), 'a 40-item quotation spilled onto a second page');
        $this->assertStringContainsString('%PDF-', $pdf);
    }

    public function test_every_document_carries_the_standard_terms_from_settings(): void
    {
        Setting::put('billing', [...Billing::settings(), 'quote_terms' => '1. Net 7 days.', 'default_terms' => '2. Net 30.']);

        // Terms typed on the request are ignored: documents always use the settings.
        $this->assertSame('1. Net 7 days.', $this->quote(['terms' => 'Typed by hand'])['terms']);
        $this->assertSame('2. Net 30.', $this->invoice(['terms' => 'Typed by hand'])['terms']);
    }

    public function test_invalid_money_and_missing_items_are_rejected(): void
    {
        $this->actingAs($this->accountant)->postJson('/admin/api/quotes', $this->payload(['items' => []]))->assertUnprocessable()->assertJsonValidationErrors('items');
        $this->actingAs($this->accountant)->postJson('/admin/api/quotes', $this->payload(['items' => [['description' => 'x', 'quantity' => 1, 'unit_price' => '12.345']]]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.unit_price');
        $this->actingAs($this->accountant)->postJson('/admin/api/quotes', $this->payload(['currency' => 'BTC']))->assertUnprocessable()->assertJsonValidationErrors('currency');
    }

    public function test_quote_lifecycle_send_view_accept_convert(): void
    {
        $q = $this->quote();
        $quote = Quote::find($q['id']);

        // Drafts are never visible through the link.
        $this->get("/billing/quote/{$quote->public_token}")->assertNotFound();

        $this->actingAs($this->accountant)->postJson("/admin/api/quotes/{$q['id']}/send", ['message' => 'Here you go!'])->assertOk()->assertJsonPath('data.status', 'sent');
        Mail::assertQueued(DocumentMail::class, fn ($m) => $m->hasTo('ada@client.test') && $m->personal === 'Here you go!');

        auth()->logout();
        $this->withoutVite();
        $this->get("/billing/quote/{$quote->public_token}")->assertOk()->assertSee('Q-')->assertSee('Accept quote')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertNotNull($quote->fresh()->viewed_at);

        $this->post("/billing/quote/{$quote->public_token}/respond", ['answer' => 'accepted'])->assertRedirect();
        $this->assertSame('accepted', $quote->fresh()->status);
        Mail::assertQueued(QuoteResponded::class);

        // A second answer is ignored.
        $this->post("/billing/quote/{$quote->public_token}/respond", ['answer' => 'declined']);
        $this->assertSame('accepted', $quote->fresh()->status);

        $invoice = $this->actingAs($this->accountant)->postJson("/admin/api/quotes/{$q['id']}/convert")->assertCreated()->json('data');
        $this->assertSame($q['total'], $invoice['total']);
        $this->assertSame('invoiced', $quote->fresh()->status);
        $this->actingAs($this->accountant)->postJson("/admin/api/quotes/{$q['id']}/convert")->assertUnprocessable();
    }

    public function test_sending_saves_the_pdf_and_attaches_it(): void
    {
        $inv = $this->invoice();
        $this->actingAs($this->accountant)->getJson("/admin/api/invoices/{$inv['id']}")->assertJsonPath('data.has_sent_pdf', false);

        $this->actingAs($this->accountant)->postJson("/admin/api/invoices/{$inv['id']}/send")->assertOk()->assertJsonPath('data.has_sent_pdf', true);

        $path = Invoice::find($inv['id'])->pdf_path;
        $this->assertStringStartsWith("documents/invoices/{$inv['number']}/", $path);
        $this->assertStringStartsWith('%PDF-', Storage::disk('local')->get($path));
        Mail::assertQueued(DocumentMail::class, function (DocumentMail $m) use ($path, $inv) {
            $attachment = $m->attachments()[0] ?? null;

            return $m->pdfPath === $path && $attachment?->as === "Invoice {$inv['number']}.pdf" && $attachment->mime === 'application/pdf';
        });

        // The saved copy is what the client received, even if the invoice is resent later.
        $sent = $this->actingAs($this->accountant)->get("/admin/api/invoices/{$inv['id']}/pdf?copy=sent")->assertOk();
        $this->assertSame(Storage::disk('local')->get($path), $sent->getContent());
    }

    public function test_admins_can_download_or_preview_any_document_as_pdf(): void
    {
        $q = $this->quote();

        $download = $this->actingAs($this->accountant)->get("/admin/api/quotes/{$q['id']}/pdf")->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringStartsWith('%PDF-', $download->getContent());
        $this->assertStringStartsWith('attachment;', $download->headers->get('Content-Disposition'));
        $this->assertStringContainsString("Quote {$q['number']}.pdf", $download->headers->get('Content-Disposition'));

        $preview = $this->actingAs($this->accountant)->get("/admin/api/quotes/{$q['id']}/pdf?inline=1")->assertOk();
        $this->assertStringStartsWith('inline;', $preview->headers->get('Content-Disposition'));

        // A draft has never been emailed, so there is no saved copy yet.
        $this->actingAs($this->accountant)->getJson("/admin/api/quotes/{$q['id']}/pdf?copy=sent")->assertNotFound();

        $marketing = User::factory()->create();
        $marketing->roles()->attach(Role::where('slug', 'marketing')->value('id'));
        $this->actingAs($marketing)->get("/admin/api/quotes/{$q['id']}/pdf")->assertForbidden();
    }

    public function test_clients_can_download_the_pdf_from_their_link_but_not_drafts(): void
    {
        $inv = $this->invoice();
        $invoice = Invoice::find($inv['id']);

        $this->get("/billing/invoice/{$invoice->public_token}/pdf")->assertNotFound();

        $this->actingAs($this->accountant)->postJson("/admin/api/invoices/{$inv['id']}/send")->assertOk();
        auth()->logout();

        $response = $this->get("/billing/invoice/{$invoice->public_token}/pdf")->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->get('/billing/invoice/'.str_repeat('A', 48).'/pdf')->assertNotFound();
    }

    public function test_payments_drive_invoice_status(): void
    {
        $inv = $this->invoice();
        $this->actingAs($this->accountant)->postJson("/admin/api/invoices/{$inv['id']}/payments", ['amount' => 100, 'paid_on' => now()->toDateString(), 'method' => 'Cash'])
            ->assertUnprocessable(); // not sent yet

        $this->actingAs($this->accountant)->postJson("/admin/api/invoices/{$inv['id']}/send")->assertOk();
        $partial = $this->actingAs($this->accountant)->postJson("/admin/api/invoices/{$inv['id']}/payments", ['amount' => '1000', 'paid_on' => now()->toDateString(), 'method' => 'Bank transfer'])
            ->assertCreated()->json('data');
        $this->assertSame('partial', $partial['status']);
        $this->assertSame(1751.1, $partial['balance']);
        $this->assertFalse($partial['editable']);

        $this->actingAs($this->accountant)->postJson("/admin/api/invoices/{$inv['id']}/payments", ['amount' => '5000', 'paid_on' => now()->toDateString(), 'method' => 'Cash'])
            ->assertUnprocessable(); // more than the balance

        $paid = $this->actingAs($this->accountant)->postJson("/admin/api/invoices/{$inv['id']}/payments", ['amount' => '1751.10', 'paid_on' => now()->toDateString(), 'method' => 'Card'])
            ->assertCreated()->json('data');
        $this->assertSame('paid', $paid['status']);
        $this->assertNotNull($paid['paid_at']);

        $this->actingAs($this->accountant)->deleteJson("/admin/api/invoices/{$inv['id']}/payments/{$paid['payments'][1]['id']}")->assertOk()->assertJsonPath('data.status', 'partial');
    }

    public function test_overdue_invoices_get_one_reminder_a_week(): void
    {
        $inv = $this->invoice(['issue_date' => now()->subDays(30)->toDateString(), 'due_date' => now()->subDays(3)->toDateString()]);
        $this->actingAs($this->accountant)->postJson("/admin/api/invoices/{$inv['id']}/send")->assertOk();
        $this->actingAs($this->accountant)->getJson("/admin/api/invoices/{$inv['id']}")->assertJsonPath('data.status', 'overdue');
        $this->actingAs($this->accountant)->getJson('/admin/api/invoices?status=overdue')->assertJsonPath('meta.total', 1);

        $this->artisan('zairon:invoice-reminders')->assertSuccessful();
        $this->artisan('zairon:invoice-reminders')->assertSuccessful();

        Mail::assertQueued(DocumentMail::class, fn ($m) => $m->variant === 'reminder' && $m->pdfPath !== null);
        Mail::assertQueuedCount(2); // the original send + exactly one reminder
        $this->assertNotNull(Invoice::find($inv['id'])->last_reminder_at);
    }

    public function test_billing_permissions(): void
    {
        $marketing = User::factory()->create();
        $marketing->roles()->attach(Role::where('slug', 'marketing')->value('id'));
        $this->actingAs($marketing)->getJson('/admin/api/invoices')->assertForbidden();
        $this->actingAs($marketing)->getJson('/admin/api/clients')->assertForbidden();

        $manager = User::factory()->create();
        $manager->roles()->attach(Role::where('slug', 'manager')->value('id'));
        $inv = $this->invoice();
        $this->actingAs($manager)->getJson("/admin/api/invoices/{$inv['id']}")->assertOk();
        $this->actingAs($manager)->deleteJson("/admin/api/invoices/{$inv['id']}")->assertForbidden(); // no billing.delete
        $this->actingAs($manager)->putJson('/admin/api/settings/billing', [])->assertForbidden(); // no settings.manage
    }

    public function test_client_links_are_unguessable_and_void_invoices_stay_on_record(): void
    {
        $inv = $this->invoice();
        $invoice = Invoice::find($inv['id']);
        $this->assertSame(48, strlen($invoice->public_token));
        $this->get('/billing/invoice/'.str_repeat('A', 48))->assertNotFound();

        $this->actingAs($this->accountant)->postJson("/admin/api/invoices/{$inv['id']}/send")->assertOk();
        $this->actingAs($this->accountant)->deleteJson("/admin/api/invoices/{$inv['id']}")->assertUnprocessable(); // sent → void instead
        $this->actingAs($this->accountant)->postJson("/admin/api/invoices/{$inv['id']}/void")->assertOk()->assertJsonPath('data.status', 'void');
        $this->assertModelExists($invoice);
    }

    public function test_won_enquiry_becomes_a_client_once(): void
    {
        $enquiry = Enquiry::create(['name' => 'Grace', 'email' => 'grace@navy.test', 'company' => 'Navy', 'details' => 'Need a compiler']);

        $first = $this->actingAs($this->accountant)->postJson("/admin/api/enquiries/{$enquiry->id}/client")->assertCreated()->json('data');
        $again = $this->actingAs($this->accountant)->postJson("/admin/api/enquiries/{$enquiry->id}/client")->assertOk()->json('data');

        $this->assertSame($first['id'], $again['id']);
        $this->assertSame('Navy', $first['company']);
    }
}
