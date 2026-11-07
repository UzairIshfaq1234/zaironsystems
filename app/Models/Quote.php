<?php

namespace App\Models;

use App\Models\Concerns\BillingDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Statuses: draft → sent → accepted | declined, then invoiced once converted.
 * "expired" is derived: sent and past valid_until.
 */
class Quote extends Model
{
    use BillingDocument;

    public const PREFIX = 'Q-ZAIRON';

    public const KIND = 'quote';

    public const STATUSES = ['draft', 'sent', 'accepted', 'declined', 'invoiced'];

    protected $fillable = ['client_id', 'status', 'issue_date', 'valid_until', 'currency', 'tax_rate', 'notes', 'terms', 'created_by'];

    protected $attributes = ['status' => 'draft', 'discount_cents' => 0, 'subtotal_cents' => 0, 'tax_cents' => 0, 'total_cents' => 0];

    protected function casts(): array
    {
        return [
            'issue_date' => 'date',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
            'viewed_at' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function displayStatus(): string
    {
        return $this->status === 'sent' && $this->valid_until?->isPast() && ! $this->valid_until->isToday() ? 'expired' : $this->status;
    }

    /** The client can still accept or decline from the shared link. */
    public function isOpen(): bool
    {
        return $this->displayStatus() === 'sent';
    }

    public function isEditable(): bool
    {
        return in_array($this->status, ['draft', 'sent'], true);
    }

    public function toAdminArray(): array
    {
        return [
            ...$this->documentArray(),
            'valid_until' => $this->valid_until?->toDateString(),
            'responded_at' => $this->responded_at?->toIso8601String(),
            'invoice_id' => $this->invoice_id,
            'editable' => $this->isEditable(),
        ];
    }
}
