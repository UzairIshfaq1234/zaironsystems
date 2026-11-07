<?php

namespace App\Models;

use App\Models\Concerns\NumberedPerYear;
use App\Support\Accounting;
use App\Support\Billing;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A bill or purchase. Posting: on `date` the category is debited and Accounts payable credited;
 * once `paid_on` is set, payable is cleared against the account it was paid from.
 */
class Expense extends Model
{
    use NumberedPerYear;

    public const PREFIX = 'EXP';

    protected $fillable = ['vendor_id', 'account_id', 'date', 'due_date', 'description', 'reference', 'currency', 'exchange_rate', 'amount_cents', 'paid_on', 'paid_from_account_id', 'created_by'];

    protected $attributes = ['amount_cents' => 0];

    protected function casts(): array
    {
        return ['date' => 'date', 'due_date' => 'date', 'paid_on' => 'date'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function paidFrom(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'paid_from_account_id');
    }

    public function scopeUnpaid(Builder $query): Builder
    {
        return $query->whereNull('paid_on');
    }

    public function dueDate(): CarbonInterface
    {
        return $this->due_date ?? $this->date;
    }

    public function status(): string
    {
        return match (true) {
            $this->paid_on !== null => 'paid',
            $this->dueDate()->lt(now()->startOfDay()) => 'overdue',
            default => 'unpaid',
        };
    }

    public function baseCents(): int
    {
        return Accounting::toBase($this->amount_cents, $this->currency, $this->exchange_rate);
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status(),
            'date' => $this->date?->toDateString(),
            'due_date' => $this->due_date?->toDateString(),
            'description' => $this->description,
            'reference' => $this->reference,
            'currency' => $this->currency,
            'exchange_rate' => $this->exchange_rate !== null ? (float) $this->exchange_rate : null,
            'amount' => Billing::fromCents($this->amount_cents),
            'paid_on' => $this->paid_on?->toDateString(),
            'vendor_id' => $this->vendor_id,
            'vendor' => $this->relationLoaded('vendor') ? $this->vendor?->name : null,
            'account_id' => $this->account_id,
            'account' => $this->relationLoaded('account') ? $this->account?->name : null,
            'paid_from_account_id' => $this->paid_from_account_id,
            'has_receipt' => (bool) $this->receipt_path,
            'receipt_name' => $this->receipt_name,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
