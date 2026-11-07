<?php

namespace App\Models;

use App\Support\Billing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    protected $fillable = ['amount_cents', 'exchange_rate', 'account_id', 'paid_on', 'method', 'reference', 'recorded_by'];

    protected function casts(): array
    {
        return ['paid_on' => 'date'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'amount' => Billing::fromCents($this->amount_cents),
            'exchange_rate' => $this->exchange_rate !== null ? (float) $this->exchange_rate : null,
            'account_id' => $this->account_id,
            'paid_on' => $this->paid_on?->toDateString(),
            'method' => $this->method,
            'reference' => $this->reference,
        ];
    }
}
