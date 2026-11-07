<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Client;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\Role;
use App\Models\User;
use App\Support\Accounting;
use App\Support\Billing;
use App\Support\Ledger;
use App\Support\Reports;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Books in PKR (the default), financial year July–June. */
class AccountingTest extends TestCase
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
        $this->accountant = User::factory()->create();
        $this->accountant->roles()->attach(Role::where('slug', 'accountant')->value('id'));
        $this->client = Client::create(['name' => 'Ada', 'company' => 'Analytical Engines', 'email' => 'ada@client.test']);
        $this->actingAs($this->accountant);
    }

    /** A sent USD invoice: 2 × 1,000 + 10% tax = USD 2,200. */
    private function sentInvoice(array $overrides = []): Invoice
    {
        $id = $this->postJson('/admin/api/invoices', [
            'client_id' => $this->client->id,
            'issue_date' => now()->subDays(10)->toDateString(),
            'due_date' => now()->addDays(4)->toDateString(),
            'currency' => 'USD',
            'exchange_rate' => '280',
            'tax_rate' => 10,
            'items' => [['description' => 'Build', 'quantity' => 2, 'unit_price' => '1000']],
            ...$overrides,
        ])->assertCreated()->json('data.id');
        $this->postJson("/admin/api/invoices/{$id}/send")->assertOk();

        return Invoice::findOrFail($id);
    }

    private function pay(Invoice $invoice, string $amount, ?string $rate, int $status = 201)
    {
        return $this->postJson("/admin/api/invoices/{$invoice->id}/payments", [
            'amount' => $amount, 'paid_on' => now()->toDateString(), 'method' => 'Bank transfer', 'exchange_rate' => $rate,
        ])->assertStatus($status);
    }

    /** Balance in the account's natural direction, in PKR. */
    private function balance(string $subtype): float
    {
        $account = Ledger::account($subtype);
        $net = Reports::balances(now()->toDateString())['net'][$account->id];

        return ($account->isDebitNormal() ? $net : -$net) / 100;
    }

    private function assertBooksBalance(): void
    {
        $this->assertTrue(Reports::trialBalance(now()->toDateString())['balanced'], 'Trial balance is out');
        $this->assertTrue(Reports::balanceSheet(now()->toDateString())['balanced'], 'Balance sheet is out');
    }

    public function test_install_creates_the_chart_of_accounts(): void
    {
        $this->assertSame(count(config('accounting.chart')), Account::count());
        foreach (['bank', 'receivable', 'payable', 'tax', 'retained', 'revenue', 'fx'] as $subtype) {
            $this->assertTrue(Ledger::account($subtype)->is_active);
        }
        $this->assertSame('PKR', Accounting::baseCurrency());
    }

    public function test_financial_year_runs_july_to_june(): void
    {
        $this->assertSame(['start' => '2026-07-01', 'end' => '2027-06-30', 'label' => 'FY 2026–27'], Accounting::fiscalYear('2026-10-16'));
        $this->assertSame(['start' => '2025-07-01', 'end' => '2026-06-30', 'label' => 'FY 2025–26'], Accounting::fiscalYear('2026-06-30'));
    }

    public function test_every_iso_currency_is_accepted(): void
    {
        $this->assertGreaterThan(150, count(config('currencies')));
        $this->postJson('/admin/api/invoices', [
            'client_id' => $this->client->id, 'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString(),
            'currency' => 'JPY', 'tax_rate' => 0, 'items' => [['description' => 'x', 'quantity' => 1, 'unit_price' => '5000']],
        ])->assertCreated();
        $this->getJson('/admin/api/settings/billing')->assertJsonPath('currencies.KES', 'Kenyan Shilling')->assertJsonPath('base_currency', 'PKR');

        // Two decimals for everything stored in hundredths (whatever ICU says about PKR or KWD); none for JPY.
        $this->assertStringEndsWith('1,234.50', Billing::format(123450, 'PKR'));
        $this->assertStringEndsWith('1,234.50', Billing::format(123450, 'KWD'));
        $this->assertStringEndsWith('1,235', Billing::format(123450, 'JPY'));
    }

    public function test_a_foreign_invoice_needs_a_rate_before_it_is_sent(): void
    {
        $id = $this->postJson('/admin/api/invoices', [
            'client_id' => $this->client->id, 'issue_date' => now()->toDateString(), 'due_date' => now()->toDateString(),
            'currency' => 'USD', 'tax_rate' => 0, 'items' => [['description' => 'x', 'quantity' => 1, 'unit_price' => '100']],
        ])->assertCreated()->json('data.id');

        $this->postJson("/admin/api/invoices/{$id}/send")->assertUnprocessable()->assertJsonValidationErrors('exchange_rate');
        Mail::assertNothingQueued();
        $this->assertSame('draft', Invoice::find($id)->status);
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_sending_an_invoice_posts_receivable_revenue_and_tax(): void
    {
        $invoice = $this->sentInvoice();

        $entry = JournalEntry::with('lines')->whereMorphedTo('source', $invoice)->sole();
        $this->assertSame(616000_00, (int) $entry->lines->sum('debit_cents'));   // USD 2,200 × 280
        $this->assertEquals(616000, $this->balance('receivable'));
        $this->assertEquals(560000, $this->balance('revenue'));
        $this->assertEquals(56000, $this->balance('tax'));
        $this->assertBooksBalance();
    }

    public function test_payments_clear_receivable_and_book_exchange_differences(): void
    {
        $invoice = $this->sentInvoice();

        $this->pay($invoice, '1000', null, 422);        // a rate is required for a USD payment
        $this->pay($invoice, '1000', '285');            // gain: 1,000 × (285 − 280) = 5,000
        $this->assertEquals(285000, $this->balance('bank'));
        $this->assertEquals(336000, $this->balance('receivable'));
        $this->assertEquals(5000, $this->balance('fx'));

        $this->pay($invoice, '1200', '275');            // loss: 1,200 × (275 − 280) = −6,000
        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertEquals(0, $this->balance('receivable'));
        $this->assertEquals(-1000, $this->balance('fx'));
        $this->assertEquals(615000, $this->balance('bank'));
        $this->assertBooksBalance();
    }

    public function test_rounding_never_strands_cents_in_receivables(): void
    {
        $invoice = $this->sentInvoice(['exchange_rate' => '279.876543', 'items' => [['description' => 'x', 'quantity' => 3, 'unit_price' => '333.33']]]);
        foreach (['100.01', '250.55', '0.33'] as $amount) {
            $this->pay($invoice, $amount, '281.13579');
        }
        $this->pay($invoice, (string) $invoice->fresh()->balanceCents() / 100, '280.5');

        $this->assertSame('paid', $invoice->fresh()->status);
        $this->assertEquals(0, $this->balance('receivable'));
        $this->assertBooksBalance();
    }

    public function test_removing_a_payment_or_voiding_reverses_the_postings(): void
    {
        $invoice = $this->sentInvoice();
        $paymentId = $this->pay($invoice, '500', '280')->json('data.payments.0.id');

        $this->deleteJson("/admin/api/invoices/{$invoice->id}/payments/{$paymentId}")->assertOk();
        $this->assertEquals(0, $this->balance('bank'));
        $this->assertEquals(616000, $this->balance('receivable'));

        $this->postJson("/admin/api/invoices/{$invoice->id}/void")->assertOk();
        $this->assertSame(0, JournalEntry::count());
        $this->assertEquals(0, $this->balance('revenue'));
    }

    public function test_closed_periods_cannot_change(): void
    {
        $invoice = $this->sentInvoice();   // issued 10 days ago
        $this->putJson('/admin/api/accounting/settings', ['base_currency' => 'PKR', 'fiscal_year_start' => 7, 'lock_date' => now()->subDays(5)->toDateString()])->assertOk();

        // A payment today is in an open period, and removing it leaves the closed invoice entry untouched.
        $paymentId = $this->pay($invoice, '500', '280')->json('data.payments.0.id');
        $this->deleteJson("/admin/api/invoices/{$invoice->id}/payments/{$paymentId}")->assertOk();

        // Changing or voiding the invoice would rewrite a closed period.
        $this->putJson("/admin/api/invoices/{$invoice->id}", [
            'client_id' => $this->client->id, 'issue_date' => $invoice->issue_date->toDateString(), 'due_date' => $invoice->due_date->toDateString(),
            'currency' => 'USD', 'exchange_rate' => '300', 'tax_rate' => 10, 'items' => [['description' => 'Build', 'quantity' => 2, 'unit_price' => '1000']],
        ])->assertUnprocessable()->assertJsonValidationErrors('date');
        $this->assertEquals(280, (float) $invoice->fresh()->exchange_rate);   // rolled back

        $this->postJson("/admin/api/invoices/{$invoice->id}/void")->assertUnprocessable();
        $this->assertSame('sent', $invoice->fresh()->status);

        $this->postJson('/admin/api/accounting/journal', $this->manualEntry(now()->subDays(6)->toDateString()))->assertUnprocessable()->assertJsonValidationErrors('date');
    }

    public function test_expenses_post_to_payables_until_paid(): void
    {
        Storage::fake('local');
        $hosting = Account::where('code', '5300')->value('id');
        $vendorId = $this->postJson('/admin/api/accounting/vendors', ['name' => 'CloudCo'])->assertCreated()->json('data.id');

        $expense = $this->post('/admin/api/accounting/expenses', [
            'vendor_id' => $vendorId, 'account_id' => $hosting, 'date' => now()->subDays(3)->toDateString(), 'due_date' => now()->addDays(7)->toDateString(),
            'description' => 'Servers', 'currency' => 'PKR', 'amount' => '45000',
            'receipt' => UploadedFile::fake()->create('invoice.pdf', 40, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertCreated()->json('data');

        $this->assertSame('unpaid', $expense['status']);
        $this->assertTrue($expense['has_receipt']);
        Storage::disk('local')->assertExists(Expense::find($expense['id'])->receipt_path);
        $this->assertEquals(45000, $this->balance('payable'));
        $this->getJson("/admin/api/accounting/expenses/{$expense['id']}/receipt")->assertOk();

        $this->postJson("/admin/api/accounting/expenses/{$expense['id']}", [
            'vendor_id' => $vendorId, 'account_id' => $hosting, 'date' => now()->subDays(3)->toDateString(),
            'description' => 'Servers', 'currency' => 'PKR', 'amount' => '45000', 'paid_on' => now()->toDateString(),
        ])->assertOk()->assertJsonPath('data.status', 'paid');
        $this->assertEquals(0, $this->balance('payable'));
        $this->assertEquals(-45000, $this->balance('bank'));

        $pl = Reports::profitAndLoss(Accounting::fiscalYear()['start'], now()->toDateString());
        $this->assertEquals(45000, $pl['expenses']['total']);
        $this->assertEquals(-45000, $pl['net_profit']);
        $this->assertBooksBalance();

        $this->deleteJson('/admin/api/accounting/vendors/'.$vendorId)->assertUnprocessable();
        $this->deleteJson("/admin/api/accounting/expenses/{$expense['id']}")->assertNoContent();
        $this->assertSame(0, JournalEntry::count());
    }

    public function test_foreign_expenses_need_a_rate(): void
    {
        $this->postJson('/admin/api/accounting/expenses', [
            'account_id' => Account::where('code', '5200')->value('id'), 'date' => now()->toDateString(),
            'description' => 'GitHub', 'currency' => 'USD', 'amount' => '21',
        ])->assertUnprocessable()->assertJsonValidationErrors('exchange_rate');
    }

    private function manualEntry(string $date, string $debit = '1000000', string $credit = '1000000'): array
    {
        return [
            'date' => $date,
            'description' => 'Opening capital',
            'lines' => [
                ['account_id' => Ledger::account('bank')->id, 'debit' => $debit],
                ['account_id' => Account::where('code', '3000')->value('id'), 'credit' => $credit],
            ],
        ];
    }

    public function test_manual_entries_must_balance(): void
    {
        $this->postJson('/admin/api/accounting/journal', $this->manualEntry(now()->toDateString(), '1000', '999'))
            ->assertUnprocessable()->assertJsonValidationErrors('lines');
        $this->postJson('/admin/api/accounting/journal', ['date' => now()->toDateString(), 'description' => 'x', 'lines' => [
            ['account_id' => Ledger::account('bank')->id, 'debit' => '5', 'credit' => '5'],
            ['account_id' => Ledger::account('revenue')->id, 'credit' => '5'],
        ]])->assertUnprocessable()->assertJsonValidationErrors('lines.0.debit');

        $id = $this->postJson('/admin/api/accounting/journal', $this->manualEntry(now()->toDateString()))->assertCreated()->json('data.id');
        $this->assertEquals(1000000, $this->balance('bank'));

        // Automatic entries can't be edited by hand.
        $invoice = $this->sentInvoice();
        $auto = JournalEntry::whereMorphedTo('source', $invoice)->value('id');
        $this->deleteJson("/admin/api/accounting/journal/{$auto}")->assertUnprocessable();
        $this->deleteJson("/admin/api/accounting/journal/{$id}")->assertNoContent();
    }

    public function test_last_years_profit_rolls_into_retained_earnings(): void
    {
        $lastYear = CarbonImmutable::parse(Accounting::fiscalYear()['start'])->subDay()->toDateString();
        $this->postJson('/admin/api/accounting/journal', ['date' => $lastYear, 'description' => 'Old sale', 'lines' => [
            ['account_id' => Ledger::account('bank')->id, 'debit' => '7000'],
            ['account_id' => Ledger::account('revenue')->id, 'credit' => '7000'],
        ]])->assertCreated();

        $this->assertEquals(0, $this->balance('revenue'));          // income restarts each financial year
        $this->assertEquals(7000, $this->balance('retained'));
        $this->assertBooksBalance();

        $this->getJson('/admin/api/accounting/reports/trial-balance')->assertOk()->assertJsonPath('data.balanced', true);
        $this->getJson('/admin/api/accounting/reports/balance-sheet')->assertOk()->assertJsonPath('data.assets.total', 7000);
        $ledger = $this->getJson('/admin/api/accounting/accounts/'.Ledger::account('bank')->id.'/ledger?from='.now()->toDateString().'&to='.now()->toDateString())->assertOk();
        $this->assertEquals(7000, $ledger->json('data.opening'));
    }

    public function test_base_currency_is_fixed_once_the_books_have_entries(): void
    {
        $this->putJson('/admin/api/accounting/settings', ['base_currency' => 'USD', 'fiscal_year_start' => 7])->assertOk();
        $this->putJson('/admin/api/accounting/settings', ['base_currency' => 'PKR', 'fiscal_year_start' => 7])->assertOk();
        $this->postJson('/admin/api/accounting/journal', $this->manualEntry(now()->toDateString()))->assertCreated();
        $this->putJson('/admin/api/accounting/settings', ['base_currency' => 'USD', 'fiscal_year_start' => 7])->assertUnprocessable();
    }

    public function test_receivables_aging(): void
    {
        $this->sentInvoice(['issue_date' => now()->subDays(80)->toDateString(), 'due_date' => now()->subDays(45)->toDateString()]);
        $this->sentInvoice();

        $aging = $this->getJson('/admin/api/accounting/reports/receivables')->assertOk()->json('data');
        $this->assertEquals(616000, $aging['totals']['d60']);
        $this->assertEquals(616000, $aging['totals']['current']);
        $this->assertEquals(1232000, $aging['total']);
    }

    public function test_system_accounts_are_protected(): void
    {
        $receivable = Ledger::account('receivable');
        $this->deleteJson("/admin/api/accounting/accounts/{$receivable->id}")->assertUnprocessable();
        $this->putJson("/admin/api/accounting/accounts/{$receivable->id}", ['code' => '1100', 'name' => 'Debtors', 'type' => 'expense', 'is_active' => false])->assertOk();
        $this->assertSame('asset', $receivable->fresh()->type);
        $this->assertTrue($receivable->fresh()->is_active);
        $this->assertSame('Debtors', $receivable->fresh()->name);

        $id = $this->postJson('/admin/api/accounting/accounts', ['code' => '1020', 'name' => 'Meezan Bank USD', 'type' => 'asset', 'subtype' => 'bank'])->assertCreated()->json('data.id');
        $this->postJson('/admin/api/accounting/accounts', ['code' => '1020', 'name' => 'Duplicate', 'type' => 'asset'])->assertUnprocessable()->assertJsonValidationErrors('code');
        $this->deleteJson("/admin/api/accounting/accounts/{$id}")->assertNoContent();
    }

    public function test_accounting_permissions(): void
    {
        $sales = User::factory()->create();
        $sales->roles()->attach(Role::where('slug', 'sales')->value('id'));
        $this->actingAs($sales)->getJson('/admin/api/accounting/overview')->assertForbidden();

        $manager = User::factory()->create();
        $manager->roles()->attach(Role::where('slug', 'manager')->value('id'));
        $this->actingAs($manager)->getJson('/admin/api/accounting/overview')->assertOk()->assertJsonPath('checks.trial_balance', true);
        $this->actingAs($manager)->postJson('/admin/api/accounting/journal', $this->manualEntry(now()->toDateString()))->assertForbidden();
        $this->actingAs($manager)->putJson('/admin/api/accounting/settings', ['base_currency' => 'PKR', 'fiscal_year_start' => 7])->assertForbidden();
    }

    public function test_expense_categories_can_be_added_used_and_switched_off(): void
    {
        $cat = $this->actingAs($this->accountant)->postJson('/admin/api/accounting/categories', ['name' => 'Office supplies'])->assertCreated()->json('data');
        $this->assertSame('expense', Account::find($cat['id'])->type);

        $this->actingAs($this->accountant)->postJson('/admin/api/accounting/expenses', [
            'account_id' => $cat['id'], 'date' => now()->toDateString(), 'description' => 'Pens', 'currency' => 'PKR', 'amount' => '1200',
        ])->assertCreated();
        $this->assertEquals(1200, $this->balance('payable'));

        $this->actingAs($this->accountant)->putJson("/admin/api/accounting/categories/{$cat['id']}", ['name' => 'Stationery', 'is_active' => false])->assertOk();
        $this->assertFalse(Account::find($cat['id'])->is_active);
        $this->assertSame('Stationery', Account::find($cat['id'])->name);
    }
}