<?php

namespace App\Http\Controllers\Admin;

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Privilege-escalation rules shared by the user and role controllers.
 * Super admins may do anything; everyone else can only hand out or manage
 * access they already hold themselves.
 */
trait Guards
{
    /** @param Collection<int, Role> $roles */
    protected function ensureCanAssign(User $actor, Collection $roles): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }
        foreach ($roles as $role) {
            abort_if($role->isSuperAdmin(), 403, 'Only a super admin can assign the Super Admin role.');
            abort_unless($actor->holdsAll($role->permissions ?? []), 403, "You can't assign \"{$role->name}\" because it grants permissions you don't have.");
        }
    }

    protected function ensureCanManage(User $actor, User $target): void
    {
        if ($actor->isSuperAdmin()) {
            return;
        }
        abort_if($target->isSuperAdmin(), 403, 'Only a super admin can change a super admin.');
        abort_unless($actor->holdsAll($target->permissions()), 403, 'This user has permissions you do not have, so you cannot change them.');
    }

    protected function ensureCanGrant(User $actor, array $permissions): void
    {
        abort_unless($actor->holdsAll($permissions), 403, "You can't grant permissions you don't have yourself.");
    }

    protected function superAdminCount(): int
    {
        return User::whereHas('roles', fn ($q) => $q->where('slug', config('permissions.super_admin_role')))->count();
    }
}
