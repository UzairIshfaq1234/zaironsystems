<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A job title belongs to one department (e.g. "Product Designer" under Design). */
class JobTitle extends HrList
{
    protected $fillable = ['name', 'department_id'];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function toAdminArray(): array
    {
        return [...parent::toAdminArray(), 'department_id' => $this->department_id];
    }
}
