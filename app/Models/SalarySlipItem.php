<?php

namespace App\Models;

use App\Support\Billing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line on a salary slip: an earning (basic, each allowance) or a deduction (unpaid leave). */
class SalarySlipItem extends Model
{
    public const EARNING = 'earning';

    public const DEDUCTION = 'deduction';

    protected $fillable = ['salary_slip_id', 'label', 'kind', 'amount_cents', 'sort'];

    protected $attributes = ['sort' => 0];

    public function slip(): BelongsTo
    {
        return $this->belongsTo(SalarySlip::class, 'salary_slip_id');
    }

    public function toArray(): array
    {
        return ['label' => $this->label, 'kind' => $this->kind, 'amount' => Billing::fromCents($this->amount_cents)];
    }
}
