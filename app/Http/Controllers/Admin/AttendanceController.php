<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\AttendanceRecord;
use App\Models\Employee;
use App\Support\Hr;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Daily attendance board, and manual corrections by a manager (the employee can also check in themselves). */
class AttendanceController extends Controller
{
    /** Every active employee for one day, with their record (or none) — the attendance board. */
    public function index(Request $request): JsonResponse
    {
        $date = $request->validate(['date' => ['nullable', 'date']])['date'] ?? Hr::workDate(now());

        $records = AttendanceRecord::whereDate('work_date', $date)->get()->keyBy('employee_id');
        $rows = Employee::query()->active()->orderBy('code')->get()->map(fn (Employee $e) => [
            'employee_id' => $e->id,
            'code' => $e->code,
            'name' => $e->name,
            'shift' => substr((string) $e->shift_start, 0, 5).'–'.substr((string) $e->shift_end, 0, 5),
            'record' => $records->get($e->id)?->toAdminArray(),
        ]);

        return response()->json([
            'date' => $date,
            'data' => $rows->values(),
            'summary' => [
                'present' => $records->whereNotNull('check_in_at')->count(),
                'late' => $records->where('late_minutes', '>', 0)->count(),
                'absent' => max($rows->count() - $records->whereNotNull('check_in_at')->count(), 0),
            ],
        ]);
    }

    /** Sets or corrects one day's check-in and check-out for an employee. Times are in the company timezone. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'employee_id' => ['required', 'integer', 'exists:employees,id'],
            'work_date' => ['required', 'date'],
            'check_in' => ['nullable', 'date_format:H:i'],
            'check_out' => ['nullable', 'date_format:H:i'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $employee = Employee::findOrFail($data['employee_id']);
        $day = CarbonImmutable::parse($data['work_date'], config('zairon.timezone'));

        $checkIn = $data['check_in'] ? $day->setTimeFromTimeString($data['check_in']) : null;
        $checkOut = $data['check_out'] ? $day->setTimeFromTimeString($data['check_out']) : null;
        abort_if($checkIn && $checkOut && $checkOut->lt($checkIn), 422, 'Check-out must be after check-in.');

        $record = AttendanceRecord::updateOrCreate(
            ['employee_id' => $employee->id, 'work_date' => $day->toDateString()],
            [
                'check_in_at' => $checkIn,
                'check_out_at' => $checkOut,
                'late_minutes' => $checkIn ? Hr::lateMinutes($employee, $checkIn) : 0,
                'note' => $data['note'] ?? null,
            ],
        );
        ActivityLog::record('attendance.recorded', "Set attendance for {$employee->name} on {$day->toDateString()}", $employee);

        return response()->json(['data' => $record->toAdminArray()]);
    }

    /** Hours and lateness for a month, one row per employee. */
    public function report(Request $request): JsonResponse
    {
        $request->validate(['month' => ['required', 'date_format:Y-m']]);
        $start = CarbonImmutable::createFromFormat('Y-m', $request->input('month'), config('zairon.timezone'))->startOfMonth();

        $rows = Employee::query()->active()->orderBy('code')->get()->map(function (Employee $employee) use ($start) {
            $records = $employee->attendance()->whereBetween('work_date', [$start->toDateString(), $start->endOfMonth()->toDateString()])->get();

            return [
                'code' => $employee->code,
                'name' => $employee->name,
                'days_present' => $records->whereNotNull('check_in_at')->count(),
                'late_days' => $records->where('late_minutes', '>', 0)->count(),
                'late_minutes' => (int) $records->sum('late_minutes'),
                'hours' => round($records->sum(fn ($r) => $r->hoursWorked() ?? 0), 2),
            ];
        });

        return response()->json(['data' => $rows->values()]);
    }
}
