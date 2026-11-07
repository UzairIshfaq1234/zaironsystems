<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/** A department (Design, Engineering…). Holds its job titles; employees are assigned to it. */
class Department extends HrList
{
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function jobTitles(): HasMany
    {
        return $this->hasMany(JobTitle::class)->orderBy('name');
    }

    public function toAdminArray(): array
    {
        return [
            ...parent::toAdminArray(),
            // Deleting a department is only allowed when it has no job titles and no people.
            'in_use' => $this->employees()->exists() || $this->jobTitles()->exists(),
            'job_titles' => $this->jobTitles->map->toAdminArray()->values(),
        ];
    }
}
