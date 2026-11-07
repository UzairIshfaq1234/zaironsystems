<?php

namespace App\Models;

use App\Models\Concerns\BillingDocument;
use App\Support\Billing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Stored statuses: draft, sent, partial, paid, void. "overdue" is derived:
 * sent/partial and past the due date.
 */
class Invoice extends Model
{
    use BillingDocument;

    public const PREFIX = 'ZRON';

    public const KIND = 'invoice';

    protected $fillable = ['client_id', 'quote_id', 'status', 'issue_date', 'due_date', 'currency', 'exchange_rate', 'tax_rate', 'notes', 'terms', 'created_by'];

    protected $attributes = ['status' => 'draft', 'paid_cents' => 0, 'discount_cents' => 0, 'subtotal_cents' => 0, 'tax_cents' => 0, 'total_cents' => 0];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'due_date' => 'date',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'paid_at' => 'datetime',
            'last_reminder_at' => 'datetime',
        ];
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->orderBy('paid_on')->orderBy('id');
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->whereIn('status', ['sent', 'partial'])->whereDate('due_date', '<', now()->toDateString());
    }

    public function isOverdue(): bool
    {
        return in_array($this->status, ['sent', 'partial'], true) && $this->due_date->lt(now()->startOfDay());
    }

    public function displayStatus(): string
    {
        return $this->isOverdue() ? 'overdue' : $this->status;
    }

    public function balanceCents(): int
    {
        return max($this->total_cents - $this->paid_cents, 0);
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'sent'], true) && $this->paid_cents === 0;
    }

    /** Recalculate paid amount and status after a payment is added or removed. */
    public function syncPayments(): void
    {
        $paid = (int) $this->payments()->sum('amount_cents');
        $status = match (true) {
            $this->status === 'void' => 'void',
            $paid >= $this->total_cents && $this->total_cents > 0 => 'paid',
            $paid > 0 => 'partial',
            $this->sent_at !== null => 'sent',
            default => 'draft',
        };

        $this->forceFill([
            'paid_cents' => $paid,
            'status' => $status,
            'paid_at' => $status === 'paid' ? ($this->paid_at ?? now()) : null,
        ])->save();
    }

    public function toAdminArray(): array
    {
        return [
            ...$this->documentArray(),
            'due_date' => $this->due_date?->toDateString(),
            'exchange_rate' => $this->exchange_rate !== null ? (float) $this->exchange_rate : null,
            'paid' => Billing::fromCents($this->paid_cents),
            'balance' => Billing::fromCents($this->balanceCents()),
            'paid_at' => $this->paid_at?->toIso8601String(),
            'quote_id' => $this->quote_id,
            'quote_number' => $this->relationLoaded('quote') ? $this->quote?->number : null,
            'last_reminder_at' => $this->last_reminder_at?->toIso8601String(),
            'editable' => $this->isEditable(),
            'payments' => $this->relationLoaded('payments') ? $this->payments->map->toAdminArray()->values() : [],
        ];
    }
}
