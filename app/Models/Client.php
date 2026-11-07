<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A billable customer, usually created from a won enquiry. */
class Client extends Model
{
    protected $fillable = ['name', 'company', 'email', 'phone', 'address', 'tax_number', 'notes', 'enquiry_id'];

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function displayName(): string
    {
        return $this->company ?: $this->name;
    }

    public function toSummaryArray(): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'company' => $this->company, 'email' => $this->email];
    }

    public function toAdminArray(): array
    {
        return [
            ...$this->only($this->fillable),
            'id' => $this->id,
            'created_at' => $this->created_at?->toIso8601String(),
            'quotes_count' => $this->quotes_count ?? null,
            'invoices_count' => $this->invoices_count ?? null,
        ];
    }
}
