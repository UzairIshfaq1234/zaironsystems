<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Arr;

/** A named set of permissions (keys from config/permissions.php) that can be assigned to users. */
class Role extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'permissions', 'is_system'];

    protected function casts(): array
    {
        return ['permissions' => 'array', 'is_system' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->slug === config('permissions.super_admin_role');
    }

    /** @return list<string> Every permission key in the catalog. */
    public static function catalog(): array
    {
        return array_keys(Arr::collapse(config('permissions.groups')));
    }
}
