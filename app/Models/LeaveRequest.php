<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A leave request. Approved requests reduce the employee's balance for their leave type. */
class LeaveRequest extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'cancelled'];

    protected $fillable = ['employee_id', 'type', 'start_date', 'end_date', 'days', 'reason', 'status', 'decided_by', 'decided_at', 'decision_note'];

    protected $attributes = ['status' => 'pending'];

    protected function casts(): array
    {
        return ['start_date' => 'date', 'end_date' => 'date', 'days' => 'float', 'decided_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'employee' => $this->relationLoaded('employee') ? $this->employee?->name : null,
            'employee_code' => $this->relationLoaded('employee') ? $this->employee?->code : null,
            'type' => $this->type,
            'type_label' => config("hr.leave_types.{$this->type}.label", ucfirst($this->type)),
            'start_date' => $this->start_date?->toDateString(),
            'end_date' => $this->end_date?->toDateString(),
            'days' => (float) $this->days,
            'reason' => $this->reason,
            'status' => $this->status,
            'decision_note' => $this->decision_note,
            'decided_at' => $this->decided_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
