<?php

namespace App\Models;

use App\Support\Billing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One employee's pay for one month. Amounts are frozen when the run is generated. */
class SalarySlip extends Model
{
    protected $fillable = ['payroll_run_id', 'employee_id', 'period', 'basic_cents', 'allowance_cents', 'unpaid_days', 'unpaid_deduction_cents', 'gross_cents', 'tax_cents', 'net_cents'];

    protected function casts(): array
    {
        return ['unpaid_days' => 'float'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SalarySlipItem::class)->orderBy('sort')->orderBy('id');
    }

    public function run(): BelongsTo
    {
        return $this->belongsTo(PayrollRun::class, 'payroll_run_id');
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'period' => $this->period,
            'basic' => Billing::fromCents($this->basic_cents),
            'allowance' => Billing::fromCents($this->allowance_cents),
            'unpaid_days' => (float) $this->unpaid_days,
            'unpaid_deduction' => Billing::fromCents($this->unpaid_deduction_cents),
            'gross' => Billing::fromCents($this->gross_cents),
            'tax' => Billing::fromCents($this->tax_cents),
            'net' => Billing::fromCents($this->net_cents),
        ];
    }
}
