<?php

namespace App\Models;

use App\Support\Billing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One allowance for one employee: which kind, and how much per month (cents). */
class EmployeeAllowance extends Model
{
    protected $fillable = ['employee_id', 'allowance_type_id', 'amount_cents'];

    protected $attributes = ['amount_cents' => 0];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(AllowanceType::class, 'allowance_type_id');
    }

    public function toAdminArray(): array
    {
        return [
            'allowance_type_id' => $this->allowance_type_id,
            'name' => $this->relationLoaded('type') ? $this->type?->name : null,
            'amount' => Billing::fromCents($this->amount_cents),
        ];
    }
}
