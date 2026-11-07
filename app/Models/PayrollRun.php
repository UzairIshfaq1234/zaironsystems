<?php

namespace App\Models;

use App\Support\Billing;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One month's payroll: a salary slip per employee, posted to the books once approved. */
class PayrollRun extends Model
{
    protected $fillable = ['period', 'status', 'gross_cents', 'tax_cents', 'net_cents', 'posted_at', 'created_by'];

    protected $attributes = ['status' => 'draft', 'gross_cents' => 0, 'tax_cents' => 0, 'net_cents' => 0];

    protected function casts(): array
    {
        return ['posted_at' => 'datetime'];
    }

    public function slips(): HasMany
    {
        return $this->hasMany(SalarySlip::class)->orderBy('id');
    }

    public function isPosted(): bool
    {
        return $this->status === 'posted';
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'period' => $this->period,
            'label' => CarbonImmutable::createFromFormat('Y-m', $this->period)->format('F Y'),
            'status' => $this->status,
            'gross' => Billing::fromCents($this->gross_cents),
            'tax' => Billing::fromCents($this->tax_cents),
            'net' => Billing::fromCents($this->net_cents),
            'posted_at' => $this->posted_at?->toIso8601String(),
            'slips_count' => $this->slips_count ?? $this->slips()->count(),
        ];
    }
}
