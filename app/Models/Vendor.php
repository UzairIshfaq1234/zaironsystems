<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A supplier the company pays (landlord, SaaS provider, contractor…). */
class Vendor extends Model
{
    protected $fillable = ['name', 'email', 'phone', 'tax_number', 'notes'];

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'tax_number' => $this->tax_number,
            'notes' => $this->notes,
            'expenses_count' => $this->expenses_count ?? null,
            'unpaid_count' => $this->unpaid_count ?? null,
        ];
    }
}
