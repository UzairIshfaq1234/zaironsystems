<?php

namespace App\Models;

use App\Support\Billing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LineItem extends Model
{
    public $timestamps = false;

    protected $fillable = ['description', 'quantity', 'unit_cents', 'amount_cents', 'sort'];

    public function document(): MorphTo
    {
        return $this->morphTo();
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'quantity' => (float) $this->quantity,
            'unit_price' => Billing::fromCents($this->unit_cents),
            'amount' => Billing::fromCents($this->amount_cents),
        ];
    }
}
