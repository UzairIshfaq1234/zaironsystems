<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin;
use App\Models\ActivityLog;
use App\Models\JournalEntry;
use App\Support\Billing;
use App\Support\Ledger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** The journal: every automatic posting plus manual entries (opening balances, salaries, adjustments…). */
class JournalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'account_id' => ['nullable', 'integer'],
            'kind' => ['nullable', Rule::in(['manual', 'auto'])],
        ]);

        $page = JournalEntry::with('lines.account')
            ->when($request->input('q'), function ($query, string $q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('number', 'like', $like)->orWhere('description', 'like', $like));
            })
            ->when($request->input('from'), fn ($q, $d) => $q->whereDate('date', '>=', $d))
            ->when($request->input('to'), fn ($q, $d) => $q->whereDate('date', '<=', $d))
            ->when($request->input('account_id'), fn ($q, $id) => $q->whereHas('lines', fn ($l) => $l->where('account_id', $id)))
            ->when($request->input('kind'), fn ($q, $kind) => $q->where('is_manual', $kind === 'manual'))
            ->orderByDesc('date')->orderByDesc('id')
            ->paginate(25);

        return response()->json(Admin::page($page, fn (JournalEntry $e) => $e->toAdminArray()));
    }

    public function show(JournalEntry $entry): JsonResponse
    {
        return response()->json(['data' => $entry->load('lines.account', 'creator')->toAdminArray()]);
    }

    public function store(Request $request): JsonResponse
    {
        $entry = Ledger::saveManual(new JournalEntry, $this->validated($request), $request->user()->id);
        ActivityLog::record('journal.created', "Posted manual journal entry {$entry->number}", $entry);

        return response()->json(['data' => $entry->toAdminArray()], 201);
    }

    public function update(Request $request, JournalEntry $entry): JsonResponse
    {
        abort_unless($entry->is_manual, 422, 'Automatic entries follow their invoice or expense. Change that document instead.');
        $entry = Ledger::saveManual($entry, $this->validated($request), $request->user()->id);
        ActivityLog::record('journal.updated', "Updated manual journal entry {$entry->number}", $entry);

        return response()->json(['data' => $entry->toAdminArray()]);
    }

    public function destroy(JournalEntry $entry): JsonResponse
    {
        abort_unless($entry->is_manual, 422, 'Automatic entries follow their invoice or expense. Change that document instead.');
        Ledger::deleteManual($entry);
        ActivityLog::record('journal.deleted', "Deleted manual journal entry {$entry->number}");

        return response()->json(status: 204);
    }

    private function validated(Request $request): array
    {
        $money = ['nullable', 'numeric', 'min:0', 'regex:/^\d{1,12}(\.\d{1,2})?$/'];
        $data = $request->validate([
            'date' => ['required', 'date'],
            'description' => ['required', 'string', 'max:250'],
            'lines' => ['required', 'array', 'min:2', 'max:50'],
            'lines.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('is_active', true)],
            'lines.*.debit' => $money,
            'lines.*.credit' => $money,
            'lines.*.memo' => ['nullable', 'string', 'max:250'],
        ], [
            'lines.min' => 'An entry needs at least two lines.',
            'lines.*.account_id.required' => 'Choose an account for every line.',
        ]);

        foreach ($data['lines'] as $i => $line) {
            if ((float) ($line['debit'] ?? 0) > 0 && (float) ($line['credit'] ?? 0) > 0) {
                throw ValidationException::withMessages(["lines.{$i}.debit" => 'A line is either a debit or a credit, not both.']);
            }
        }

        return [
            ...$data,
            'lines' => array_map(fn ($l) => [
                'account_id' => $l['account_id'],
                'debit' => Billing::toCents($l['debit'] ?? 0),
                'credit' => Billing::toCents($l['credit'] ?? 0),
                'memo' => $l['memo'] ?? null,
            ], array_values($data['lines'])),
        ];
    }
}
