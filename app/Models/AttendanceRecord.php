<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One working day for one employee: when they checked in and out, and how late they were. */
class AttendanceRecord extends Model
{
    protected $fillable = ['employee_id', 'work_date', 'check_in_at', 'check_out_at', 'late_minutes', 'note'];

    protected $attributes = ['late_minutes' => 0];

    protected function casts(): array
    {
        return ['work_date' => 'date', 'check_in_at' => 'datetime', 'check_out_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** Hours worked, to two decimals; null until checked out. */
    public function hoursWorked(): ?float
    {
        if (! $this->check_in_at || ! $this->check_out_at) {
            return null;
        }

        return round($this->check_in_at->diffInMinutes($this->check_out_at) / 60, 2);
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'employee_id' => $this->employee_id,
            'work_date' => $this->work_date?->toDateString(),
            'check_in_at' => $this->check_in_at?->toIso8601String(),
            'check_out_at' => $this->check_out_at?->toIso8601String(),
            'hours' => $this->hoursWorked(),
            'late_minutes' => $this->late_minutes,
            'note' => $this->note,
        ];
    }
}
