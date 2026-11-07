<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A manager's review of an employee for one period. The employee can read it and add a reply. */
class PerformanceReview extends Model
{
    public const SCORE_LABELS = [1 => 'Needs improvement', 2 => 'Below expectations', 3 => 'Meets expectations', 4 => 'Exceeds expectations', 5 => 'Outstanding'];

    protected $fillable = ['employee_id', 'reviewer_id', 'period', 'score', 'strengths', 'improvements', 'goals', 'employee_comment', 'acknowledged_at'];

    protected function casts(): array
    {
        return ['score' => 'integer', 'acknowledged_at' => 'datetime'];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'period' => $this->period,
            'score' => $this->score,
            'score_label' => self::SCORE_LABELS[$this->score] ?? null,
            'strengths' => $this->strengths,
            'improvements' => $this->improvements,
            'goals' => $this->goals,
            'reviewer' => $this->relationLoaded('reviewer') ? $this->reviewer?->name : null,
            'employee_comment' => $this->employee_comment,
            'acknowledged' => $this->acknowledged_at !== null,
            'acknowledged_at' => $this->acknowledged_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
