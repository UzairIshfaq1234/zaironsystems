<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Employee;
use App\Models\PerformanceReview;
use App\Notifications\PortalAlert;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Performance reviews written by managers. Employees read them in the portal and can reply. */
class ReviewController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $request->validate(['employee_id' => ['nullable', 'integer', 'exists:employees,id']]);

        $reviews = PerformanceReview::with(['employee', 'reviewer'])
            ->when($request->input('employee_id'), fn ($q, $id) => $q->where('employee_id', $id))
            ->orderByDesc('period')->orderByDesc('id')->limit(200)->get();

        return response()->json(['data' => $reviews->map(fn (PerformanceReview $r) => [...$r->toAdminArray(), 'employee' => $r->employee->name, 'employee_code' => $r->employee->code, 'employee_id' => $r->employee_id])->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'period' => ['required', 'string', 'max:20', 'regex:/^\d{4}-(Q[1-4]|\d{2})$/'],
            'score' => ['required', 'integer', 'between:1,5'],
            'strengths' => ['nullable', 'string', 'max:3000'],
            'improvements' => ['nullable', 'string', 'max:3000'],
            'goals' => ['nullable', 'string', 'max:3000'],
        ], ['period.regex' => 'Use a period like 2026-Q4 or 2026-03.']);

        $employee = Employee::findOrFail($data['employee_id']);
        $review = PerformanceReview::updateOrCreate(
            ['employee_id' => $employee->id, 'period' => $data['period']],
            [...collect($data)->except(['employee_id', 'period'])->all(), 'reviewer_id' => $request->user()->id, 'acknowledged_at' => null],
        );
        $employee->notify(new PortalAlert('review', "New review for {$data['period']}", 'Your manager has written your review. Read it and reply in the portal.', '/reviews'));
        ActivityLog::record('review.saved', "Saved the {$data['period']} review for {$employee->name}", $employee);

        return response()->json(['data' => $review->load('reviewer')->toAdminArray()], 201);
    }
}
