<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Expense categories (Rent, Software, Travel…). Each category is an expense account in the books, so
 * expenses post to it correctly. Categories in use are renamed or switched off, never deleted.
 */
class ExpenseCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Account::where('type', 'expense')->orderBy('code')->get()->map(fn (Account $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->name,
                'is_active' => $a->is_active,
                'in_use' => $a->lines()->exists() || \App\Models\Expense::where('account_id', $a->id)->exists(),
            ])->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:120']]);
        $account = Account::create([
            'code' => $this->nextCode(),
            'name' => $data['name'],
            'type' => 'expense',
            'is_active' => true,
        ]);
        ActivityLog::record('expense_category.created', "Added expense category “{$account->name}”", $account);

        return response()->json(['data' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'is_active' => true, 'in_use' => false]], 201);
    }

    public function update(Request $request, Account $account): JsonResponse
    {
        abort_unless($account->type === 'expense', 404);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
        $account->update($data);
        ActivityLog::record('expense_category.updated', "Updated expense category “{$account->name}”", $account);

        return response()->json(['data' => ['id' => $account->id, 'code' => $account->code, 'name' => $account->name, 'is_active' => $account->is_active]]);
    }

    /** The next free code in the expense range (5xxx), after the highest one in use. */
    private function nextCode(): string
    {
        $highest = Account::where('type', 'expense')->pluck('code')->filter(fn (string $code) => ctype_digit($code))->map(fn (string $code) => (int) $code)->max() ?? 0;

        return (string) max($highest + 10, 5000);
    }
}
