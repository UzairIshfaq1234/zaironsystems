<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A ledger account in the chart of accounts. */
class Account extends Model
{
    public const TYPES = ['asset' => 'Assets', 'liability' => 'Liabilities', 'equity' => 'Equity', 'income' => 'Income', 'expense' => 'Expenses'];

    /** Subtypes people can choose; the others (receivable, payable, …) belong to system accounts only. */
    public const MONEY_SUBTYPES = ['bank', 'cash'];

    protected $fillable = ['code', 'name', 'type', 'subtype', 'description', 'is_active'];

    protected $attributes = ['is_active' => true, 'is_system' => false];

    protected function casts(): array
    {
        return ['is_system' => 'boolean', 'is_active' => 'boolean'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalLine::class);
    }

    /** Assets and expenses grow with debits; liabilities, equity and income with credits. */
    public function isDebitNormal(): bool
    {
        return in_array($this->type, ['asset', 'expense'], true);
    }

    public function isProfitAndLoss(): bool
    {
        return in_array($this->type, ['income', 'expense'], true);
    }

    public function label(): string
    {
        return "{$this->code} · {$this->name}";
    }

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'type' => $this->type,
            'subtype' => $this->subtype,
            'description' => $this->description,
            'is_system' => $this->is_system,
            'is_active' => $this->is_active,
        ];
    }
}
