<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Models\Vendor;
use App\Support\Accounting;
use App\Support\Billing;
use App\Support\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bills and purchases. Each save re-posts the expense to the ledger in the same transaction.
 * Receipts are stored on the private disk under random names and only downloadable here.
 */
class ExpenseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['paid', 'unpaid', 'overdue'])],
            'vendor_id' => ['nullable', 'integer'],
            'account_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $today = now()->toDateString();

        $page = Expense::with('vendor', 'account')
            ->when($request->input('q'), function ($query, string $q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('description', 'like', $like)->orWhere('reference', 'like', $like)
                    ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', $like)));
            })
            ->when($request->input('status'), fn ($query, $status) => match ($status) {
                'paid' => $query->whereNotNull('paid_on'),
                'unpaid' => $query->unpaid(),
                'overdue' => $query->unpaid()->whereRaw('COALESCE(due_date, date) < ?', [$today]),
            })
            ->when($request->input('vendor_id'), fn ($q, $id) => $q->where('vendor_id', $id))
            ->when($request->input('account_id'), fn ($q, $id) => $q->where('account_id', $id))
            ->when($request->input('from'), fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->input('to'), fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(20);

        return response()->json(Admin::page($page, fn (Expense $e) => $e->toAdminArray()));
    }

    /** Everything the expense form needs: categories, money accounts, vendors and currencies. */
    public function options(): JsonResponse
    {
        $accounts = Account::where('is_active', true)->orderBy('code')->get();

        return response()->json([
            'categories' => $accounts->filter(fn (Account $a) => self::isCategory($a))->map(fn (Account $a) => ['id' => $a->id, 'name' => $a->label(), 'type' => $a->type])->values(),
            'money_accounts' => $accounts->whereIn('subtype', Account::MONEY_SUBTYPES)->map(fn (Account $a) => ['id' => $a->id, 'name' => $a->label()])->values(),
            'vendors' => Vendor::orderBy('name')->limit(1000)->get(['id', 'name']),
            'currencies' => Billing::currencies(),
            'base_currency' => Accounting::baseCurrency(),
        ]);
    }

    public function show(Expense $expense): JsonResponse
    {
        return response()->json(['data' => $expense->load('vendor', 'account')->toAdminArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $expense = $this->save($request, new Expense(['created_by' => $request->user()->id]));
        ActivityLog::record('expense.created', "Recorded expense {$expense->number} ({$this->money($expense)})", $expense);

        return response()->json(['data' => $expense->toAdminArray()], 201);
    }

    public function update(Request $request, Expense $expense): JsonResponse
    {
        $this->save($request, $expense);
        ActivityLog::record('expense.updated', "Updated expense {$expense->number}", $expense);

        return response()->json(['data' => $expense->toAdminArray()]);
    }

    public function destroy(Expense $expense): JsonResponse
    {
        DB::transaction(function () use ($expense) {
            Ledger::syncExpense($expense, deleting: true);
            $expense->delete();
        });
        if ($expense->receipt_path) {
            Storage::disk('local')->delete($expense->receipt_path);
        }
        ActivityLog::record('expense.deleted', "Deleted expense {$expense->number}");

        return response()->json(status: 204);
    }

    public function receipt(Expense $expense): StreamedResponse
    {
        abort_unless($expense->receipt_path && Storage::disk('local')->exists($expense->receipt_path), 404);
        ActivityLog::record('expense.receipt', "Downloaded the receipt on {$expense->number}", $expense);

        return Storage::disk('local')->download($expense->receipt_path, $expense->receipt_name ?: 'receipt');
    }

    private function save(Request $request, Expense $expense): Expense
    {
        $base = Accounting::baseCurrency();
        $money = Rule::exists('accounts', 'id')->whereIn('subtype', Account::MONEY_SUBTYPES)->where('is_active', true);
        $data = $request->validate([
            'vendor_id' => ['nullable', 'integer', 'exists:vendors,id'],
            'account_id' => ['required', 'integer', function ($attr, $value, $fail) {
                $account = Account::where('is_active', true)->find($value);
                if (! $account || ! self::isCategory($account)) {
                    $fail('Choose an expense category.');
                }
            }],
            'date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:date'],
            'description' => ['required', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:120'],
            'currency' => ['required', Rule::in(array_keys(Billing::currencies()))],
            'exchange_rate' => ['nullable', "required_unless:currency,{$base}", ...Accounting::RATE_RULES],
            'amount' => ['required', 'numeric', 'gt:0', 'regex:/^\d{1,10}(\.\d{1,2})?$/'],
            'paid_on' => ['nullable', 'date', 'before_or_equal:today'],
            'paid_from_account_id' => ['nullable', 'integer', $money],
            'receipt' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,webp'],
            'remove_receipt' => ['nullable', 'boolean'],
        ], ['exchange_rate.required_unless' => "Enter the exchange rate: 1 {$request->input('currency')} = how many {$base}."]);

        $file = $request->file('receipt');
        $newPath = $file?->store('receipts');
        $oldPath = $expense->receipt_path;

        try {
            DB::transaction(function () use ($expense, $data, $file, $newPath, $request) {
                $expense->fill([
                    ...collect($data)->except(['amount', 'receipt', 'remove_receipt'])->all(),
                    'exchange_rate' => Accounting::normaliseRate($data['currency'], $data['exchange_rate'] ?? null),
                    'amount_cents' => Billing::toCents($data['amount']),
                    'paid_from_account_id' => isset($data['paid_on']) ? ($data['paid_from_account_id'] ?? null) : null,
                ]);
                if ($newPath) {
                    $expense->forceFill(['receipt_path' => $newPath, 'receipt_name' => Str::limit(basename($file->getClientOriginalName()), 175)]);
                } elseif ($request->boolean('remove_receipt')) {
                    $expense->forceFill(['receipt_path' => null, 'receipt_name' => null]);
                }
                $expense->save();
                Ledger::syncExpense($expense);
            });
        } catch (\Throwable $e) {
            if ($newPath) {
                Storage::disk('local')->delete($newPath);
            }
            throw $e;
        }

        if ($oldPath && $oldPath !== $expense->receipt_path) {
            Storage::disk('local')->delete($oldPath);
        }

        return $expense->load('vendor', 'account');
    }

    /** Expense categories: expense accounts, plus asset accounts for things you buy and keep (equipment, prepayments). */
    private static function isCategory(Account $account): bool
    {
        return $account->type === 'expense' || ($account->type === 'asset' && $account->subtype === null);
    }

    private function money(Expense $expense): string
    {
        return Billing::format($expense->amount_cents, $expense->currency);
    }
}
