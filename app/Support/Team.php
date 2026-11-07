<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

/** Who on the team should hear about something. */
final class Team
{
    /** @return list<string> Emails of active admins holding $permission (super admins included). */
    public static function emailsWith(string $permission): array
    {
        return self::usersWith($permission)->pluck('email')->values()->all();
    }

    /** @return Collection<int, User> Active admins holding $permission (super admins included). */
    public static function usersWith(string $permission): Collection
    {
        return User::query()->where('is_active', true)->with('roles')->get()
            ->filter(fn (User $user) => $user->hasPermission($permission))
            ->values();
    }
}
