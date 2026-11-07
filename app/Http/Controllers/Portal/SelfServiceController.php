<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Admin\AuthController as AdminAuth;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PerformanceReview;
use App\Models\SalarySlip;
use App\Notifications\PortalAlert;
use App\Support\Hr;
use App\Support\SalarySlipPdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Everything an employee does in the portal. Every query starts from the signed-in employee, so
 * nobody can read or change another person's records by changing an id in the address.
 */
class SelfServiceController extends Controller
{
    /** Today's shift, whether they have checked in or out, and this week's totals. */
    public function home(Request $request): JsonResponse
    {
        $employee = $request->user('employee');
        $today = Hr::workDate(now());
        $record = $employee->attendance()->whereDate('work_date', $today)->first();

        return response()->json([
            'date' => $today,
            'shift' => ['start' => substr((string) $employee->shift_start, 0, 5), 'end' => substr((string) $employee->shift_end, 0, 5)],
            'now' => now()->setTimezone(config('zairon.timezone'))->toIso8601String(),
            'record' => $record?->toAdminArray(),
            'can_check_in' => $record === null,
            'can_check_out' => $record !== null && $record->check_in_at && ! $record->check_out_at,
            'upcoming_leave' => $employee->leaveRequests()->whereIn('status', ['pending', 'approved'])->whereDate('end_date', '>=', $today)->limit(3)->get()->map->toAdminArray()->values(),
            'unread_reviews' => $employee->reviews()->whereNull('acknowledged_at')->count(),
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $employee = $request->user('employee');
        $now = now();
        $day = Hr::workDate($now);

        if ($employee->attendance()->whereDate('work_date', $day)->exists()) {
            throw ValidationException::withMessages(['attendance' => 'You have already checked in today.']);
        }

        $record = AttendanceRecord::create([
            'employee_id' => $employee->id,
            'work_date' => $day,
            'check_in_at' => $now,
            'late_minutes' => Hr::lateMinutes($employee, $now),
        ]);

        return response()->json(['data' => $record->toAdminArray(), 'message' => $record->late_minutes ? "Checked in. You are {$record->late_minutes} minutes late." : 'Checked in. Have a great shift.'], 201);
    }

    public function checkOut(Request $request): JsonResponse
    {
        $employee = $request->user('employee');
        $record = $employee->attendance()->whereDate('work_date', Hr::workDate(now()))->first();

        if (! $record || ! $record->check_in_at) {
            throw ValidationException::withMessages(['attendance' => 'Check in first.']);
        }
        if ($record->check_out_at) {
            throw ValidationException::withMessages(['attendance' => 'You have already checked out today.']);
        }

        $record->forceFill(['check_out_at' => now()])->save();

        return response()->json(['data' => $record->toAdminArray(), 'message' => 'Checked out. Thanks for today.']);
    }

    /** Attendance for one month (default: this month), newest first. */
    public function attendance(Request $request): JsonResponse
    {
        $request->validate(['month' => ['nullable', 'date_format:Y-m']]);
        $start = CarbonImmutable::createFromFormat('Y-m', $request->input('month', now(config('zairon.timezone'))->format('Y-m')), config('zairon.timezone'))->startOfMonth();

        $records = $request->user('employee')->attendance()
            ->whereBetween('work_date', [$start->toDateString(), $start->endOfMonth()->toDateString()])
            ->get();

        return response()->json([
            'month' => $start->format('Y-m'),
            'data' => $records->map->toAdminArray()->values(),
            'summary' => [
                'days_present' => $records->whereNotNull('check_in_at')->count(),
                'late_days' => $records->where('late_minutes', '>', 0)->count(),
                'hours' => round($records->sum(fn ($r) => $r->hoursWorked() ?? 0), 2),
            ],
        ]);
    }

    public function leave(Request $request): JsonResponse
    {
        $employee = $request->user('employee');

        return response()->json([
            'balances' => Hr::leaveBalances($employee),
            'data' => $employee->leaveRequests()->limit(100)->get()->map->toAdminArray()->values(),
            'types' => collect(config('hr.leave_types'))->map(fn ($t, $key) => ['value' => $key, 'label' => $t['label']])->values(),
        ]);
    }

    public function requestLeave(Request $request): JsonResponse
    {
        $employee = $request->user('employee');
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(config('hr.leave_types')))],
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $days = Hr::daysBetween($data['start_date'], $data['end_date']);
        if ($days > 90) {
            throw ValidationException::withMessages(['end_date' => 'A single request can be at most 90 days. Split longer leave into two requests.']);
        }

        $overlaps = $employee->leaveRequests()->whereIn('status', ['pending', 'approved'])
            ->whereDate('start_date', '<=', $data['end_date'])->whereDate('end_date', '>=', $data['start_date'])->exists();
        if ($overlaps) {
            throw ValidationException::withMessages(['start_date' => 'You already have leave requested for part of these dates.']);
        }

        if (! Hr::fitsBalance($employee, $data['type'], $days, $data['start_date'])) {
            $label = strtolower(config("hr.leave_types.{$data['type']}.label"));
            throw ValidationException::withMessages(['type' => "You do not have {$days} day(s) of {$label} left. Choose unpaid leave or fewer days."]);
        }

        $leave = $employee->leaveRequests()->create([...$data, 'days' => $days, 'status' => 'pending']);

        $type = config("hr.leave_types.{$data['type']}.label");
        PortalAlert::toTeam('hr.manage', new PortalAlert(
            'leave',
            "{$employee->name} asked for {$days} day(s) of ".strtolower($type),
            "{$data['start_date']} to {$data['end_date']}".(($data['reason'] ?? null) ? " · {$data['reason']}" : ''),
            '/leave',
        ));

        return response()->json(['data' => $leave->toAdminArray(), 'message' => 'Request sent to your manager.'], 201);
    }

    public function cancelLeave(Request $request, LeaveRequest $leave): JsonResponse
    {
        abort_unless($leave->employee_id === $request->user('employee')->id, 404);
        abort_unless($leave->status === 'pending', 422, 'Only requests that are still pending can be cancelled.');
        $leave->forceFill(['status' => 'cancelled'])->save();

        return response()->json(['data' => $leave->toAdminArray(), 'message' => 'Request cancelled.']);
    }

    /** Salary slips the company has posted (drafts stay hidden until payroll is finalised). */
    public function slips(Request $request): JsonResponse
    {
        $slips = $request->user('employee')->salarySlips()
            ->whereHas('run', fn ($q) => $q->where('status', 'posted'))
            ->get()->map(fn (SalarySlip $slip) => $slip->toArray())->values();

        return response()->json(['data' => $slips]);
    }

    public function slipPdf(Request $request, SalarySlip $slip): Response
    {
        abort_unless($slip->employee_id === $request->user('employee')->id, 404);
        abort_unless($slip->run->status === 'posted', 404);

        return SalarySlipPdf::response(SalarySlipPdf::render($slip), SalarySlipPdf::filename($slip));
    }

    public function reviews(Request $request): JsonResponse
    {
        return response()->json(['data' => $request->user('employee')->reviews()->with('reviewer')->get()->map->toAdminArray()->values()]);
    }

    /** The employee reads a review and may add a reply; reading it counts as acknowledging it. */
    public function replyToReview(Request $request, PerformanceReview $review): JsonResponse
    {
        abort_unless($review->employee_id === $request->user('employee')->id, 404);
        $data = $request->validate(['comment' => ['nullable', 'string', 'max:2000']]);

        $review->forceFill([
            'employee_comment' => $data['comment'] ?? $review->employee_comment,
            'acknowledged_at' => $review->acknowledged_at ?? now(),
        ])->save();

        return response()->json(['data' => $review->load('reviewer')->toAdminArray(), 'message' => 'Thanks. Your reply is saved.']);
    }

    public function changePassword(Request $request): JsonResponse
    {
        /** @var Employee $employee */
        $employee = $request->user('employee');
        $data = $request->validate([
            'current_password' => ['required', 'string', 'max:200'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', AdminAuth::passwordRule()],
        ]);

        if (! Hash::check($data['current_password'], $employee->password)) {
            throw ValidationException::withMessages(['current_password' => 'Your current password is not right.']);
        }

        $employee->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();

        return response()->json(['message' => 'Password changed.']);
    }
}
