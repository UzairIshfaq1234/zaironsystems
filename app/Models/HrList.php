<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Base for the simple managed lists (departments, job titles). Each list is edited on its own;
 * an entry in use by someone cannot be deleted.
 */
abstract class HrList extends Model
{
    protected $fillable = ['name'];

    /** Employees who use this entry (the foreign key is named after the list). */
    abstract public function employees(): HasMany;

    public function toAdminArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'in_use' => $this->employees()->exists(),
        ];
    }
}
