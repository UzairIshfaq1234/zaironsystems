<?php

namespace App\Models;

use App\Models\Concerns\NumberedPerYear;
use App\Support\Billing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A balanced double-entry transaction. Automatic entries belong to a source (invoice or expense)
 * and are kept in step with it by App\Support\Ledger; manual entries are typed in by an accountant.
 */
class JournalEntry extends Model
{
    use NumberedPerYear;

    public const PREFIX = 'JE';

    protected $fillable = ['date', 'description', 'source_key', 'is_manual', 'created_by'];

    protected $attributes = ['is_manual' => false];

    protected function casts(): array
    {
        return ['date' => 'date', 'is_manual' => 'boolean'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class)->orderBy('id');
    }

    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Where an automatic entry came from, for linking back to it in the portal. */
    public function sourceInfo(): ?array
    {
        return match ($this->source_type) {
            Invoice::class => ['kind' => 'invoice', 'id' => $this->source_id],
            Expense::class => ['kind' => 'expense', 'id' => $this->source_id],
            default => null,
        };
    }

    public function toAdminArray(): array
    {
        $lines = $this->relationLoaded('lines') ? $this->lines : null;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date?->toDateString(),
            'description' => $this->description,
            'is_manual' => $this->is_manual,
            'source' => $this->sourceInfo(),
            'total' => $lines ? Billing::fromCents((int) $lines->sum('debit_cents')) : null,
            'lines' => $lines ? $lines->map->toAdminArray()->values() : [],
            'created_by' => $this->relationLoaded('creator') ? $this->creator?->name : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
