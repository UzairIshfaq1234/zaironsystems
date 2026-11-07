<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'is_active', 'invited_at', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['is_active' => true];

    /** @var list<string>|null Memoised permission keys for this request. */
    private ?array $permissionCache = null;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'invited_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->loadMissing('roles')->roles->contains(fn (Role $role) => $role->isSuperAdmin());
    }

    /** @return list<string> */
    public function permissions(): array
    {
        return $this->permissionCache ??= $this->isSuperAdmin()
            ? Role::catalog()
            : $this->roles->pluck('permissions')->flatten()->unique()->values()->all();
    }

    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions(), true);
    }

    /** True when every permission in $permissions is one this user already holds (prevents privilege escalation). */
    public function holdsAll(array $permissions): bool
    {
        return array_diff($permissions, $this->permissions()) === [];
    }

    public function flushPermissionCache(): void
    {
        $this->permissionCache = null;
        $this->unsetRelation('roles');
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }
}
