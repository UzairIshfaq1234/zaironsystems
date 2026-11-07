<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\LeaveRequest;
use App\Notifications\PortalAlert;
use App\Support\Hr;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Leave requests: list and approve or reject. Employees create them in the portal. */
class LeaveController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['status' => ['nullable', Rule::in(LeaveRequest::STATUSES)]]);
        $status = $request->input('status', 'pending');

        $page = LeaveRequest::with('employee')
            ->when($status !== 'all', fn (Builder $q) => $q->where('status', $status))
            ->orderByDesc('start_date')->orderByDesc('id')
            ->paginate(25);

        return response()->json([
            'data' => $page->getCollection()->map->toAdminArray()->values(),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'pending' => LeaveRequest::where('status', 'pending')->count(),
        ]);
    }

    public function decide(Request $request, LeaveRequest $leave): JsonResponse
    {
        abort_unless($leave->status === 'pending', 422, 'This request has already been decided.');
        $data = $request->validate([
            'decision' => ['required', Rule::in(['approved', 'rejected'])],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        // A request can only be approved while the employee still has the days.
        if ($data['decision'] === 'approved' && ! Hr::fitsBalance($leave->employee, $leave->type, (float) $leave->days, $leave->start_date)) {
            abort(422, 'Not enough '.strtolower(config("hr.leave_types.{$leave->type}.label")).' left for this request.');
        }

        $leave->forceFill([
            'status' => $data['decision'],
            'decided_by' => $request->user()->id,
            'decided_at' => now(),
            'decision_note' => $data['note'] ?? null,
        ])->save();
        $leave->loadMissing('employee');
        $leave->employee->notify(new PortalAlert(
            'leave',
            'Your leave was '.$data['decision'],
            "{$leave->days} day(s) from {$leave->start_date->toDateString()}".(($data['note'] ?? null) ? " · {$data['note']}" : ''),
            '/leave',
        ));
        ActivityLog::record("leave.{$data['decision']}", ucfirst($data['decision'])." {$leave->employee->name}'s leave ({$leave->days} day(s))", $leave);

        return response()->json(['data' => $leave->load('employee')->toAdminArray()]);
    }
}
