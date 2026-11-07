<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\LeaveRequest;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** Shift timing, lateness, leave days and leave balances. Rules live in config/hr.php. */
final class Hr
{
    /** Minutes late for a check-in: 0 when on time or within the grace period, otherwise minutes after the shift start. */
    public static function lateMinutes(Employee $employee, CarbonInterface $checkIn): int
    {
        $minutesAfterStart = (int) floor(self::shiftStart($employee, $checkIn)->diffInMinutes($checkIn, false));

        return $minutesAfterStart > config('hr.late_grace_minutes') ? $minutesAfterStart : 0;
    }

    /** The shift start on the day of `$at`, in the company timezone. */
    public static function shiftStart(Employee $employee, CarbonInterface $at): CarbonImmutable
    {
        $local = CarbonImmutable::instance($at)->setTimezone(config('zairon.timezone'));
        [$h, $m] = array_map('intval', explode(':', substr((string) $employee->shift_start, 0, 5)));

        return $local->setTime($h, $m);
    }

    /** Working day for a moment: the company's local date. */
    public static function workDate(CarbonInterface $at): string
    {
        return CarbonImmutable::instance($at)->setTimezone(config('zairon.timezone'))->toDateString();
    }

    /** Inclusive calendar days from start to end (a single day is 1). */
    public static function daysBetween(string $start, string $end): float
    {
        return (float) CarbonImmutable::parse($start)->diffInDays(CarbonImmutable::parse($end)) + 1;
    }

    /** Days of `$type` already approved (or still pending, when `$pending`) in the year containing `$date`. */
    public static function usedDays(Employee $employee, string $type, CarbonInterface|string $date, bool $pending = false): float
    {
        $year = Accounting::fiscalYear($date);

        return (float) LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('type', $type)
            ->whereIn('status', $pending ? ['approved', 'pending'] : ['approved'])
            ->whereDate('start_date', '<=', $year['end'])
            ->whereDate('end_date', '>=', $year['start'])
            ->sum('days');
    }

    /**
     * Balance per leave type for the current financial year. Annual leave includes unused days carried
     * over from last year (up to the carry-over cap). Unpaid leave has no balance.
     *
     * @return list<array{type: string, label: string, allowance: float|null, carried: float, used: float, pending: float, remaining: float|null}>
     */
    public static function leaveBalances(Employee $employee, CarbonInterface|string|null $date = null): array
    {
        $date = CarbonImmutable::parse($date ?? 'today');
        $previous = $date->subYear();

        return collect(config('hr.leave_types'))->map(function (array $rules, string $type) use ($employee, $date, $previous) {
            $carried = 0.0;
            // Only someone employed last year has unused days to carry forward.
            if ($rules['carry_over'] > 0 && $rules['allowance'] !== null && CarbonImmutable::parse($employee->joined_on)->lte($previous->endOfYear())) {
                $lastYearLeft = (float) $rules['allowance'] - self::usedDays($employee, $type, $previous);
                $carried = min(max($lastYearLeft, 0), (float) $rules['carry_over']);
            }
            $used = self::usedDays($employee, $type, $date);
            $pending = self::usedDays($employee, $type, $date, pending: true) - $used;
            $allowance = $rules['allowance'] === null ? null : (float) $rules['allowance'] + $carried;

            return [
                'type' => $type,
                'label' => $rules['label'],
                'allowance' => $allowance,
                'carried' => $carried,
                'used' => $used,
                'pending' => $pending,
                'remaining' => $allowance === null ? null : max($allowance - $used - $pending, 0),
            ];
        })->values()->all();
    }

    /** Whether `$days` more of `$type` fit the balance (unpaid leave always fits). */
    public static function fitsBalance(Employee $employee, string $type, float $days, CarbonInterface|string $date): bool
    {
        $balance = collect(self::leaveBalances($employee, $date))->firstWhere('type', $type);

        return $balance['remaining'] === null || $days <= $balance['remaining'];
    }
}
