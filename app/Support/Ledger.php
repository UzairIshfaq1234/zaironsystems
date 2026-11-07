<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\PayrollRun;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * The posting engine. Invoices, payments and expenses never write journal lines themselves:
 * after any change they call syncInvoice()/syncExpense(), which works out the entries the
 * document should have and brings the journal in line, touching only entries that changed
 * and refusing to touch closed periods. Every entry is checked to balance before it is saved.
 *
 * Accrual basis:
 *   Invoice issued  Dr Accounts receivable  / Cr Revenue + Cr Sales tax payable
 *   Payment         Dr Bank                 / Cr Accounts receivable (± exchange gain/loss)
 *   Expense         Dr Expense category     / Cr Accounts payable
 *   Expense paid    Dr Accounts payable     / Cr Bank (or cash)
 */
final class Ledger
{
    /** The account the ledger posts to for a role: receivable, payable, tax, retained, revenue, fx, bank. */
    public static function account(string $subtype): Account
    {
        return Account::where('subtype', $subtype)->where('is_active', true)
            ->orderByDesc('is_system')->orderBy('code')
            ->firstOr(fn () => throw new LogicException("No active '{$subtype}' account in the chart of accounts."));
    }

    public static function syncInvoice(Invoice $invoice): void
    {
        $invoice->load('client', 'payments');
        $desired = [];

        if (! in_array($invoice->status, ['draft', 'void'], true) && $invoice->total_cents > 0) {
            $total = Accounting::toBase($invoice->total_cents, $invoice->currency, $invoice->exchange_rate);
            $tax = Accounting::toBase($invoice->tax_cents, $invoice->currency, $invoice->exchange_rate);
            $receivable = self::account('receivable')->id;

            $desired['invoice'] = [
                'date' => $invoice->issue_date->toDateString(),
                'description' => "Invoice {$invoice->number} · {$invoice->client->displayName()}",
                'lines' => [
                    ['account_id' => $receivable, 'debit' => $total],
                    ['account_id' => self::account('revenue')->id, 'credit' => $total - $tax],
                    ['account_id' => self::account('tax')->id, 'credit' => $tax],
                ],
            ];

            // Each payment clears receivable at the invoice's rate; the payment that settles the invoice
            // clears whatever is left, so rounding never strands a few cents in receivables.
            $cleared = 0;
            $unpaid = $invoice->total_cents;
            foreach ($invoice->payments as $payment) { // ordered by paid_on, id
                $unpaid -= $payment->amount_cents;
                $received = Accounting::toBase($payment->amount_cents, $invoice->currency, $payment->exchange_rate);
                $settled = $unpaid <= 0 ? $total - $cleared : Accounting::toBase($payment->amount_cents, $invoice->currency, $invoice->exchange_rate);
                $cleared += $settled;

                $desired["payment:{$payment->id}"] = [
                    'date' => $payment->paid_on->toDateString(),
                    'description' => "Payment for {$invoice->number} · {$payment->method}".($payment->reference ? " · {$payment->reference}" : ''),
                    'lines' => [
                        ['account_id' => $payment->account_id ?? self::account('bank')->id, 'debit' => $received],
                        ['account_id' => $receivable, 'credit' => $settled],
                        ['account_id' => self::account('fx')->id, 'credit' => $received - $settled, 'memo' => 'Exchange difference'],
                    ],
                ];
            }
        }

        self::sync($invoice, $desired);
    }

    public static function syncExpense(Expense $expense, bool $deleting = false): void
    {
        $desired = [];

        if (! $deleting && $expense->amount_cents > 0) {
            $expense->load('vendor');
            $amount = $expense->baseCents();
            $payable = self::account('payable')->id;
            $label = "{$expense->number} · ".($expense->vendor ? "{$expense->vendor->name} · " : '').$expense->description;

            $desired['bill'] = [
                'date' => $expense->date->toDateString(),
                'description' => "Expense {$label}",
                'lines' => [
                    ['account_id' => $expense->account_id, 'debit' => $amount],
                    ['account_id' => $payable, 'credit' => $amount],
                ],
            ];
            if ($expense->paid_on) {
                $desired['payment'] = [
                    'date' => $expense->paid_on->toDateString(),
                    'description' => "Paid {$label}",
                    'lines' => [
                        ['account_id' => $payable, 'debit' => $amount],
                        ['account_id' => $expense->paid_from_account_id ?? self::account('bank')->id, 'credit' => $amount],
                    ],
                ];
            }
        }

        self::sync($expense, $desired);
    }

    /**
     * A posted payroll month: Dr Salaries (gross), Cr Salaries payable (net pay), Cr Withholding tax payable.
     * Drafts post nothing.
     */
    public static function syncPayrollRun(PayrollRun $run): void
    {
        $desired = [];

        if ($run->isPosted() && $run->gross_cents > 0) {
            $desired['payroll'] = [
                'date' => CarbonImmutable::createFromFormat('Y-m', $run->period)->endOfMonth()->toDateString(),
                'description' => "Salaries for {$run->period}",
                'lines' => [
                    ['account_id' => self::accountByCode('5000')->id, 'debit' => $run->gross_cents],
                    ['account_id' => self::accountByCode('2300')->id, 'credit' => $run->net_cents],
                    ['account_id' => self::accountByCode('2200')->id, 'credit' => $run->tax_cents],
                ],
            ];
        }

        self::sync($run, $desired);
    }

    /** The account with this code, for postings that use a fixed account from the starter chart. */
    private static function accountByCode(string $code): Account
    {
        return Account::where('code', $code)->firstOr(fn () => throw new LogicException("Account {$code} is missing from the chart of accounts."));
    }

    /**
     * Creates or replaces a manual journal entry.
     *
     * @param  array{date: string, description: string, lines: list<array{account_id: int, debit?: int, credit?: int, memo?: ?string}>}  $data
     */
    public static function saveManual(JournalEntry $entry, array $data, ?int $userId): JournalEntry
    {
        return DB::transaction(function () use ($entry, $data, $userId) {
            if ($entry->exists) {
                Accounting::assertOpen($entry->date);
            }
            Accounting::assertOpen($data['date']);
            $entry->forceFill(['is_manual' => true, 'created_by' => $entry->created_by ?? $userId]);

            return self::write($entry, ['date' => $data['date'], 'description' => $data['description'], 'lines' => self::normalise($data['lines'], 'lines')]);
        });
    }

    public static function deleteManual(JournalEntry $entry): void
    {
        Accounting::assertOpen($entry->date);
        $entry->delete();
    }

    /**
     * Makes the source's automatic entries match `$desired` (keyed by source_key).
     *
     * @param  array<string, array{date: string, description: string, lines: list<array>}>  $desired
     */
    private static function sync(Model $source, array $desired): void
    {
        DB::transaction(function () use ($source, $desired) {
            $existing = JournalEntry::with('lines')->whereMorphedTo('source', $source)->get()->keyBy('source_key');

            foreach ($existing as $key => $entry) {
                if (! isset($desired[$key])) {
                    Accounting::assertOpen($entry->date);
                    $entry->delete();
                }
            }

            foreach ($desired as $key => $want) {
                $want['lines'] = self::normalise($want['lines']);
                $entry = $existing->get($key);
                if ($entry && self::fingerprint($entry->date->toDateString(), $entry->description, $entry->lines->toArray()) === self::fingerprint($want['date'], $want['description'], $want['lines'])) {
                    continue;
                }
                if ($entry) {
                    Accounting::assertOpen($entry->date);
                }
                Accounting::assertOpen($want['date']);

                $entry ??= (new JournalEntry(['source_key' => $key]))->source()->associate($source);
                self::write($entry, $want);
            }
        });
    }

    private static function write(JournalEntry $entry, array $want): JournalEntry
    {
        $entry->fill(['date' => $want['date'], 'description' => Str::limit($want['description'], 250)])->save();
        $entry->lines()->delete();
        $entry->lines()->createMany($want['lines']);

        return $entry->load('lines.account');
    }

    /**
     * Turns [account_id, debit|credit] rows into stored lines: negative amounts switch sides, zero lines
     * are dropped, and the result must balance with at least two lines.
     */
    private static function normalise(array $lines, ?string $errorField = null): array
    {
        $out = [];
        foreach ($lines as $line) {
            $net = (int) ($line['debit'] ?? 0) - (int) ($line['credit'] ?? 0);
            if ($net !== 0) {
                $out[] = [
                    'account_id' => (int) $line['account_id'],
                    'debit_cents' => max($net, 0),
                    'credit_cents' => max(-$net, 0),
                    'memo' => $line['memo'] ?? null,
                ];
            }
        }

        $debits = array_sum(array_column($out, 'debit_cents'));
        $credits = array_sum(array_column($out, 'credit_cents'));
        if ($debits !== $credits || count($out) < 2) {
            $message = count($out) < 2 ? 'An entry needs at least one debit and one credit.' : 'Debits and credits must be equal (difference: '.Billing::fromCents(abs($debits - $credits)).').';
            if ($errorField) {
                throw ValidationException::withMessages([$errorField => $message]);
            }
            throw new LogicException("Unbalanced automatic entry: {$message}");
        }

        return $out;
    }

    private static function fingerprint(string $date, string $description, array $lines): string
    {
        $rows = array_map(fn ($l) => implode(':', [$l['account_id'], $l['debit_cents'], $l['credit_cents'], $l['memo'] ?? '']), $lines);
        sort($rows);

        return $date.'|'.Str::limit($description, 250).'|'.implode(',', $rows);
    }
}
