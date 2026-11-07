<?php

namespace App\Support;

use App\Models\Account;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalLine;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Financial statements built from the journal (base-currency cents in, major units out).
 * Income and expense accounts reset each financial year: profit from earlier years is
 * shown inside Retained earnings, so the balance sheet and trial balance always agree.
 */
final class Reports
{
    /** Debit and credit totals per account for entries dated in [from, to] (open-ended when null). */
    public static function movements(?string $from, ?string $to): Collection
    {
        return JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->when($from, fn ($q) => $q->whereDate('journal_entries.date', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('journal_entries.date', '<=', $to))
            ->groupBy('journal_lines.account_id')
            ->selectRaw('journal_lines.account_id, SUM(journal_lines.debit_cents) AS debit, SUM(journal_lines.credit_cents) AS credit')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->account_id => ['debit' => (int) $r->debit, 'credit' => (int) $r->credit]]);
    }

    /** Net debit balance (debits − credits) of every account as of `$asOf`, with P&L accounts for the current year only. */
    public static function balances(string $asOf): array
    {
        $fy = Accounting::fiscalYear($asOf);
        $priorEnd = CarbonImmutable::parse($fy['start'])->subDay()->toDateString();
        $accounts = Account::orderBy('code')->get();
        $all = self::movements(null, $asOf);
        $prior = self::movements(null, $priorEnd);

        $net = [];
        $priorProfit = 0;
        foreach ($accounts as $a) {
            $total = ($all[$a->id]['debit'] ?? 0) - ($all[$a->id]['credit'] ?? 0);
            if ($a->isProfitAndLoss()) {
                $before = ($prior[$a->id]['debit'] ?? 0) - ($prior[$a->id]['credit'] ?? 0);
                $priorProfit -= $before;   // credit balances are profit
                $total -= $before;
            }
            $net[$a->id] = $total;
        }
        $retained = Ledger::account('retained');
        $net[$retained->id] -= $priorProfit;

        return ['accounts' => $accounts, 'net' => $net, 'fiscal_year' => $fy];
    }

    public static function trialBalance(string $asOf): array
    {
        ['accounts' => $accounts, 'net' => $net, 'fiscal_year' => $fy] = self::balances($asOf);
        $rows = $accounts->filter(fn (Account $a) => $net[$a->id] !== 0)->map(fn (Account $a) => [
            ...self::accountRef($a),
            'debit' => $net[$a->id] > 0 ? self::major($net[$a->id]) : 0,
            'credit' => $net[$a->id] < 0 ? self::major(-$net[$a->id]) : 0,
        ])->values();

        $debit = array_sum(array_filter($net, fn ($v) => $v > 0));
        $credit = -array_sum(array_filter($net, fn ($v) => $v < 0));

        return [
            'as_of' => $asOf,
            'fiscal_year' => $fy,
            'rows' => $rows,
            'totals' => ['debit' => self::major($debit), 'credit' => self::major($credit)],
            'balanced' => $debit === $credit,
        ];
    }

    public static function profitAndLoss(string $from, string $to): array
    {
        $moves = self::movements($from, $to);
        $section = function (string $type) use ($moves) {
            $sign = $type === 'income' ? -1 : 1;
            $rows = Account::where('type', $type)->orderBy('code')->get()
                ->map(fn (Account $a) => [...self::accountRef($a), 'cents' => $sign * (($moves[$a->id]['debit'] ?? 0) - ($moves[$a->id]['credit'] ?? 0))])
                ->filter(fn ($r) => $r['cents'] !== 0)->values();

            return ['rows' => $rows->map(fn ($r) => [...collect($r)->except('cents')->all(), 'amount' => self::major($r['cents'])])->values(), 'cents' => $rows->sum('cents')];
        };
        $income = $section('income');
        $expenses = $section('expense');

        return [
            'from' => $from,
            'to' => $to,
            'income' => ['rows' => $income['rows'], 'total' => self::major($income['cents'])],
            'expenses' => ['rows' => $expenses['rows'], 'total' => self::major($expenses['cents'])],
            'net_profit' => self::major($income['cents'] - $expenses['cents']),
        ];
    }

    public static function balanceSheet(string $asOf): array
    {
        ['accounts' => $accounts, 'net' => $net, 'fiscal_year' => $fy] = self::balances($asOf);
        $section = function (string $type) use ($accounts, $net) {
            $sign = $type === 'asset' ? 1 : -1;
            $rows = $accounts->where('type', $type)->map(fn (Account $a) => [...self::accountRef($a), 'cents' => $sign * $net[$a->id]])->filter(fn ($r) => $r['cents'] !== 0)->values();

            return [$rows, $rows->sum('cents')];
        };
        $format = fn ($rows) => $rows->map(fn ($r) => [...collect($r)->except('cents')->all(), 'amount' => self::major($r['cents'])])->values();

        [$assets, $assetTotal] = $section('asset');
        [$liabilities, $liabilityTotal] = $section('liability');
        [$equity, $equityTotal] = $section('equity');
        $profit = -$accounts->filter->isProfitAndLoss()->sum(fn (Account $a) => $net[$a->id]);
        $equityRows = $format($equity);
        if ($profit !== 0) {
            $equityRows->push(['id' => null, 'code' => '', 'name' => "Profit for {$fy['label']} to date", 'type' => 'equity', 'amount' => self::major($profit)]);
        }

        return [
            'as_of' => $asOf,
            'fiscal_year' => $fy,
            'assets' => ['rows' => $format($assets), 'total' => self::major($assetTotal)],
            'liabilities' => ['rows' => $format($liabilities), 'total' => self::major($liabilityTotal)],
            'equity' => ['rows' => $equityRows, 'total' => self::major($equityTotal + $profit)],
            'balanced' => $assetTotal === $liabilityTotal + $equityTotal + $profit,
        ];
    }

    /** Every posting to one account in [from, to], with the opening balance and a running balance in the account's natural direction. */
    public static function generalLedger(Account $account, string $from, string $to): array
    {
        // Income/expense accounts open at zero each financial year; balance-sheet accounts carry everything before.
        $openingFrom = $account->isProfitAndLoss() ? Accounting::fiscalYear($from)['start'] : null;
        $before = CarbonImmutable::parse($from)->subDay()->toDateString();
        $opening = self::movements($openingFrom, $before)[$account->id] ?? ['debit' => 0, 'credit' => 0];
        $sign = $account->isDebitNormal() ? 1 : -1;
        $balance = $openingBalance = $sign * ($opening['debit'] - $opening['credit']);

        $lines = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->where('journal_lines.account_id', $account->id)
            ->whereDate('journal_entries.date', '>=', $from)
            ->whereDate('journal_entries.date', '<=', $to)
            ->orderBy('journal_entries.date')->orderBy('journal_entries.id')
            ->select('journal_lines.*', 'journal_entries.date', 'journal_entries.number', 'journal_entries.description', 'journal_entries.id as entry_id')
            ->limit(5000)
            ->get()
            ->map(function ($l) use (&$balance, $sign) {
                $balance += $sign * ($l->debit_cents - $l->credit_cents);

                return [
                    'id' => $l->id,
                    'entry_id' => $l->entry_id,
                    'date' => CarbonImmutable::parse($l->date)->toDateString(),
                    'number' => $l->number,
                    'description' => $l->memo ? "{$l->description} — {$l->memo}" : $l->description,
                    'debit' => self::major($l->debit_cents),
                    'credit' => self::major($l->credit_cents),
                    'balance' => self::major($balance),
                ];
            });

        return [
            'account' => $account->toAdminArray(),
            'from' => $from,
            'to' => $to,
            'opening' => self::major($openingBalance),
            'closing' => self::major($balance),
            'totals' => ['debit' => round($lines->sum('debit'), 2), 'credit' => round($lines->sum('credit'), 2)],
            'lines' => $lines,
        ];
    }

    public const AGING_BUCKETS = ['current' => 'Not yet due', 'd30' => '1–30 days', 'd60' => '31–60 days', 'd90' => '61–90 days', 'older' => 'Over 90 days'];

    /** Unpaid invoices by how long they are overdue, in the base currency. */
    public static function receivablesAging(): array
    {
        $rows = Invoice::with('client')->whereIn('status', ['sent', 'partial'])->orderBy('due_date')->get()
            ->map(fn (Invoice $i) => self::agingRow($i->due_date, rescue(fn () => Accounting::toBase($i->balanceCents(), $i->currency, $i->exchange_rate), 0, false), [
                'id' => $i->id,
                'number' => $i->number,
                'party' => $i->client->displayName(),
                'currency' => $i->currency,
                'amount' => Billing::fromCents($i->balanceCents()),
            ]));

        return self::agingReport($rows);
    }

    /** Unpaid expenses by how long they are overdue, in the base currency. */
    public static function payablesAging(): array
    {
        $rows = Expense::with('vendor')->unpaid()->orderBy('date')->get()
            ->map(fn (Expense $e) => self::agingRow($e->dueDate(), rescue(fn () => $e->baseCents(), 0, false), [
                'id' => $e->id,
                'number' => $e->number,
                'party' => $e->vendor?->name ?? $e->description,
                'currency' => $e->currency,
                'amount' => Billing::fromCents($e->amount_cents),
            ]));

        return self::agingReport($rows);
    }

    private static function agingRow($due, int $baseCents, array $fields): array
    {
        $days = (int) CarbonImmutable::parse($due)->startOfDay()->diffInDays(CarbonImmutable::today(), false);
        $bucket = match (true) {
            $days <= 0 => 'current',
            $days <= 30 => 'd30',
            $days <= 60 => 'd60',
            $days <= 90 => 'd90',
            default => 'older',
        };

        return [...$fields, 'due_date' => CarbonImmutable::parse($due)->toDateString(), 'days_overdue' => max($days, 0), 'bucket' => $bucket, 'base_cents' => $baseCents];
    }

    private static function agingReport(Collection $rows): array
    {
        $totals = collect(self::AGING_BUCKETS)->map(fn ($_, $key) => self::major($rows->where('bucket', $key)->sum('base_cents')));

        return [
            'buckets' => self::AGING_BUCKETS,
            'rows' => $rows->map(fn ($r) => [...collect($r)->except('base_cents')->all(), 'base_amount' => self::major($r['base_cents'])])->values(),
            'totals' => $totals,
            'total' => self::major($rows->sum('base_cents')),
        ];
    }

    private static function accountRef(Account $a): array
    {
        return ['id' => $a->id, 'code' => $a->code, 'name' => $a->name, 'type' => $a->type];
    }

    private static function major(int $cents): float
    {
        return Billing::fromCents($cents);
    }
}
