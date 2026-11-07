<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\Invoice;
use App\Models\JournalEntry;
use App\Models\JournalLine;
use App\Models\Setting;
use App\Support\Accounting;
use App\Support\Billing;
use App\Support\Reports;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Accounting dashboard and settings (base currency, financial year, period lock). */
class AccountingController extends Controller
{
    public function overview(): JsonResponse
    {
        $today = now()->toDateString();
        $fy = Accounting::fiscalYear();
        ['accounts' => $accounts, 'net' => $net] = Reports::balances($today);
        $sum = fn (callable $filter, int $sign = 1) => Billing::fromCents($sign * $accounts->filter($filter)->sum(fn (Account $a) => $net[$a->id]));
        $pl = Reports::profitAndLoss($fy['start'], $today);
        $trial = Reports::trialBalance($today);
        $sheet = Reports::balanceSheet($today);

        return response()->json([
            'base_currency' => Accounting::baseCurrency(),
            'fiscal_year' => $fy,
            'lock_date' => Accounting::settings()['lock_date'],
            'kpis' => [
                'cash' => $sum(fn (Account $a) => in_array($a->subtype, Account::MONEY_SUBTYPES, true)),
                'receivable' => $sum(fn (Account $a) => $a->subtype === 'receivable'),
                'payable' => $sum(fn (Account $a) => $a->subtype === 'payable', -1),
                'tax_payable' => $sum(fn (Account $a) => $a->subtype === 'tax', -1),
                'income' => $pl['income']['total'],
                'expenses' => $pl['expenses']['total'],
                'profit' => $pl['net_profit'],
            ],
            'money_accounts' => $accounts->whereIn('subtype', Account::MONEY_SUBTYPES)->map(fn (Account $a) => [
                'id' => $a->id, 'name' => $a->name, 'code' => $a->code, 'balance' => Billing::fromCents($net[$a->id]),
            ])->values(),
            'months' => $this->months($fy['start'], $fy['end']),
            'counts' => [
                'overdue_invoices' => Invoice::overdue()->count(),
                'unpaid_expenses' => Expense::unpaid()->count(),
                'entries' => JournalEntry::count(),
            ],
            'checks' => [
                'trial_balance' => $trial['balanced'],
                'balance_sheet' => $sheet['balanced'],
                'missing_rates' => Invoice::whereIn('status', ['sent', 'partial', 'paid'])->where('currency', '!=', Accounting::baseCurrency())->whereNull('exchange_rate')->count(),
            ],
        ]);
    }

    public function settings(): JsonResponse
    {
        return response()->json($this->settingsPayload());
    }

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'base_currency' => ['required', Rule::in(array_keys(Billing::currencies()))],
            'fiscal_year_start' => ['required', 'integer', 'between:1,12'],
            'lock_date' => ['nullable', 'date', 'before_or_equal:today'],
        ]);
        $current = Accounting::settings();
        if ($data['base_currency'] !== $current['base_currency'] && JournalEntry::exists()) {
            abort(422, 'The base currency can only change before anything is posted to the books.');
        }

        $lock = isset($data['lock_date']) ? CarbonImmutable::parse($data['lock_date'])->toDateString() : null;
        Setting::put('accounting', [...$data, 'lock_date' => $lock]);
        ActivityLog::record('accounting.settings', $lock !== $current['lock_date']
            ? ($lock ? "Closed the books up to {$lock}" : 'Reopened all accounting periods')
            : 'Updated the accounting settings');

        return response()->json($this->settingsPayload());
    }

    private function settingsPayload(): array
    {
        return [
            'data' => Accounting::settings(),
            'currencies' => Billing::currencies(),
            'months' => Accounting::MONTHS,
            'fiscal_year' => Accounting::fiscalYear(),
            'base_locked' => JournalEntry::exists(),
        ];
    }

    /** Income and expenses per month across the financial year (base currency). */
    private function months(string $start, string $end): array
    {
        $rows = JournalLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_lines.journal_entry_id')
            ->join('accounts', 'accounts.id', '=', 'journal_lines.account_id')
            ->whereIn('accounts.type', ['income', 'expense'])
            ->whereDate('journal_entries.date', '>=', $start)
            ->whereDate('journal_entries.date', '<=', $end)
            ->groupBy('journal_entries.date', 'accounts.type')
            ->selectRaw('journal_entries.date AS day, accounts.type AS type, SUM(journal_lines.debit_cents) AS debit, SUM(journal_lines.credit_cents) AS credit')
            ->get();

        $months = [];
        for ($m = CarbonImmutable::parse($start); $m->lte($end); $m = $m->addMonth()) {
            $months[$m->format('Y-m')] = ['month' => $m->format('Y-m'), 'income' => 0, 'expenses' => 0];
        }
        foreach ($rows as $r) {
            $key = substr((string) $r->day, 0, 7);
            if (! isset($months[$key])) {
                continue;
            }
            if ($r->type === 'income') {
                $months[$key]['income'] += $r->credit - $r->debit;
            } else {
                $months[$key]['expenses'] += $r->debit - $r->credit;
            }
        }

        return array_values(array_map(fn ($m) => [...$m, 'income' => Billing::fromCents($m['income']), 'expenses' => Billing::fromCents($m['expenses'])], $months));
    }
}
