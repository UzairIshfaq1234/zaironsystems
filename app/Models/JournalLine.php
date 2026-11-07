<?php

namespace App\Models;

use App\Support\Billing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One side of a journal entry, in base-currency cents. Exactly one of debit/credit is non-zero. */
class JournalLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['account_id', 'debit_cents', 'credit_cents', 'memo'];

    protected $attributes = ['debit_cents' => 0, 'credit_cents' => 0];

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'account_id' => $this->account_id,
            'account' => $this->relationLoaded('account') ? $this->account?->label() : null,
            'debit' => Billing::fromCents($this->debit_cents),
            'credit' => Billing::fromCents($this->credit_cents),
            'memo' => $this->memo,
        ];
    }
}
