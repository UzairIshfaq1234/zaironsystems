<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Expense;
use App\Support\Billing;
use App\Support\Reports;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Chart of accounts, with each account's balance today and its general ledger. */
class AccountController extends Controller
{
    public function index(): JsonResponse
    {
        ['accounts' => $accounts, 'net' => $net] = Reports::balances(now()->toDateString());
        $used = Account::whereHas('lines')->pluck('id')->flip();

        return response()->json([
            'data' => $accounts->map(fn (Account $a) => [
                ...$a->toAdminArray(),
                'balance' => Billing::fromCents(($a->isDebitNormal() ? 1 : -1) * $net[$a->id]),
                'in_use' => $used->has($a->id),
            ])->values(),
            'types' => Account::TYPES,
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $account = Account::create($this->validated($request));
        ActivityLog::record('account.created', "Added account {$account->label()}", $account);

        return response()->json(['data' => $account->toAdminArray()], 201);
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        $data = $this->validated($request, $account);
        if ($account->is_system) {
            // System accounts are what the ledger posts to: they can be renamed, never retyped or switched off.
            $data = collect($data)->only(['code', 'name', 'description'])->all();
        } elseif ($account->lines()->exists() && $data['type'] !== $account->type) {
            abort(422, 'This account already has postings, so its type can no longer change.');
        }
        $account->update($data);
        ActivityLog::record('account.updated', "Updated account {$account->label()}", $account);

        return response()->json(['data' => $account->toAdminArray()]);
    }

    public function destroy(Account $account): JsonResponse
    {
        abort_if($account->is_system, 422, 'System accounts are used for automatic postings and cannot be deleted.');
        abort_if($account->lines()->exists() || Expense::where('account_id', $account->id)->exists(), 422, 'This account has postings. Deactivate it instead.');
        $account->delete();
        ActivityLog::record('account.deleted', "Deleted account {$account->label()}");

        return response()->json(status: 204);
    }

    public function ledger(Request $request, Account $account): JsonResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from'],
        ]);

        return response()->json(['data' => Reports::generalLedger($account, $data['from'], $data['to'])]);
    }

    private function validated(Request $request, ?Account $account = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[0-9A-Za-z.\-]+$/', Rule::unique('accounts', 'code')->ignore($account?->id)],
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(array_keys(Account::TYPES))],
            'subtype' => ['nullable', Rule::in(Account::MONEY_SUBTYPES)],
            'description' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ], ['code.regex' => 'Use letters, numbers, dots or dashes.']);

        // Only asset accounts can hold money (bank / cash).
        return [...$data, 'subtype' => $data['type'] === 'asset' ? ($data['subtype'] ?? null) : null];
    }
}
