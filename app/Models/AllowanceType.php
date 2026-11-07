<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** A kind of allowance (House rent, Medical…). Each employee gets their own amount for each type. */
class AllowanceType extends HrList
{
    protected $fillable = ['name', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(EmployeeAllowance::class);
    }

    public function toAdminArray(): array
    {
        return [...parent::toAdminArray(), 'is_active' => $this->is_active];
    }
}
