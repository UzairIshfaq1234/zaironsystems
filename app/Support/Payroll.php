<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\PayrollRun;
use App\Models\SalarySlipItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Monthly payroll. Each slip: basic + allowance, less unpaid leave (salary ÷ days_per_month per day),
 * with income tax withheld from the annualised gross. All amounts in cents.
 */
final class Payroll
{
    /** Creates (or rebuilds, while still a draft) one slip per active employee for the month. */
    public static function generate(PayrollRun $run): PayrollRun
    {
        abort_if($run->isPosted(), 422, 'This month has been posted to the books and can no longer change.');

        return DB::transaction(function () use ($run) {
            $run->slips()->delete();
            $start = CarbonImmutable::createFromFormat('Y-m', $run->period)->startOfMonth();
            $end = $start->endOfMonth();

            $totals = ['gross' => 0, 'tax' => 0, 'net' => 0];
            Employee::query()->active()->with('allowances.type')->orderBy('code')->get()
                ->each(function (Employee $employee) use ($run, $start, $end, &$totals) {
                    ['slip' => $slip, 'items' => $items] = self::slipFor($employee, $run->period, $start, $end);
                    $created = $run->slips()->create(['employee_id' => $employee->id, ...$slip]);
                    $created->items()->createMany($items);
                    $totals['gross'] += $slip['gross_cents'];
                    $totals['tax'] += $slip['tax_cents'];
                    $totals['net'] += $slip['net_cents'];
                });

            $run->forceFill(['gross_cents' => $totals['gross'], 'tax_cents' => $totals['tax'], 'net_cents' => $totals['net']])->save();

            return $run->load('slips');
        });
    }

    /**
     * The amounts for one employee's slip in a month (unsaved): the totals, and one line per item
     * (basic pay, each allowance, and any unpaid-leave deduction).
     *
     * @return array{slip: array<string, mixed>, items: list<array<string, mixed>>}
     */
    public static function slipFor(Employee $employee, string $period, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $unpaidDays = self::unpaidDaysIn($employee, $start, $end);
        $allowances = $employee->allowances;
        $allowanceCents = (int) $allowances->sum('amount_cents');
        $fullCents = $employee->basic_salary_cents + $allowanceCents;
        $deduction = (int) round($fullCents / config('hr.days_per_month') * $unpaidDays);
        $gross = max($fullCents - $deduction, 0);
        $tax = self::monthlyTax($gross);

        $items = [['label' => 'Basic salary', 'kind' => SalarySlipItem::EARNING, 'amount_cents' => $employee->basic_salary_cents, 'sort' => 0]];
        foreach ($allowances as $i => $allowance) {
            $items[] = ['label' => $allowance->type->name, 'kind' => SalarySlipItem::EARNING, 'amount_cents' => $allowance->amount_cents, 'sort' => $i + 1];
        }
        if ($deduction > 0) {
            $items[] = ['label' => 'Unpaid leave ('.rtrim(rtrim(number_format($unpaidDays, 1), '0'), '.').' day(s))', 'kind' => SalarySlipItem::DEDUCTION, 'amount_cents' => $deduction, 'sort' => 100];
        }

        return [
            'slip' => [
                'period' => $period,
                'basic_cents' => $employee->basic_salary_cents,
                'allowance_cents' => $allowanceCents,
                'unpaid_days' => $unpaidDays,
                'unpaid_deduction_cents' => $deduction,
                'gross_cents' => $gross,
                'tax_cents' => $tax,
                'net_cents' => $gross - $tax,
            ],
            'items' => $items,
        ];
    }
    /** Unpaid leave days overlapping the month (approved requests only). */
    public static function unpaidDaysIn(Employee $employee, CarbonImmutable $start, CarbonImmutable $end): float
    {
        return LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('type', 'unpaid')
            ->approved()
            ->whereDate('start_date', '<=', $end->toDateString())
            ->whereDate('end_date', '>=', $start->toDateString())
            ->get()
            ->sum(function (LeaveRequest $leave) use ($start, $end) {
                $from = max($leave->start_date->toDateString(), $start->toDateString());
                $to = min($leave->end_date->toDateString(), $end->toDateString());

                return Hr::daysBetween($from, $to);
            });
    }

    /**
     * Monthly income tax from the annual slabs in config/hr.php. The slabs are in rupees; the gross is
     * annualised (× 12), taxed band by band, and the year's tax is spread over 12 months.
     */
    public static function monthlyTax(int $grossCents): int
    {
        $annualRupees = $grossCents / 100 * 12;
        $tax = 0.0;
        $floor = 0.0;
        foreach (config('hr.tax_slabs') as $slab) {
            $ceiling = $slab['up_to'] ?? INF;
            if ($annualRupees > $floor) {
                $tax += (min($annualRupees, $ceiling) - $floor) * $slab['rate'];
            }
            $floor = $ceiling;
        }

        return (int) round($tax / 12 * 100);
    }

    /** Deletes an unposted run (its slips go with it). */
    public static function delete(PayrollRun $run): void
    {
        abort_if($run->isPosted(), 422, 'Posted payroll cannot be deleted.');
        $run->delete();
    }
}
