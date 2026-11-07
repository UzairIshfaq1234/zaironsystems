<?php

namespace App\Http\Resources;

use App\Models\ActivityLog;
use App\Models\Enquiry;
use App\Models\JobApplication;
use App\Models\JobOpening;
use App\Models\Role;
use App\Models\Subscriber;
use App\Models\User;

/**
 * JSON shapes the admin portal reads. Kept in one place so every endpoint returns the same fields
 * and nothing sensitive (passwords, tokens, file paths) can slip into a response.
 */
final class Admin
{
    public static function me(User $user): array
    {
        return [
            ...self::user($user),
            'permissions' => $user->permissions(),
            'is_super_admin' => $user->isSuperAdmin(),
        ];
    }

    public static function user(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'is_active' => $user->is_active,
            'is_super_admin' => $user->isSuperAdmin(),
            'pending_invite' => $user->invited_at !== null && $user->last_login_at === null,
            'last_login_at' => $user->last_login_at?->toIso8601String(),
            'created_at' => $user->created_at?->toIso8601String(),
            'roles' => $user->roles->map(fn (Role $r) => ['id' => $r->id, 'name' => $r->name, 'slug' => $r->slug])->values(),
        ];
    }

    public static function role(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'slug' => $role->slug,
            'description' => $role->description,
            'permissions' => $role->isSuperAdmin() ? Role::catalog() : array_values($role->permissions ?? []),
            'is_system' => $role->is_system,
            'users_count' => $role->users_count ?? $role->users()->count(),
            'updated_at' => $role->updated_at?->toIso8601String(),
        ];
    }

    public static function enquiry(Enquiry $e, bool $full = false): array
    {
        $base = [
            'id' => $e->id,
            'name' => $e->name,
            'email' => $e->email,
            'company' => $e->company,
            'service' => $e->service,
            'services' => $e->services ?? [],
            'plan' => $e->plan,
            'budget' => $e->budget,
            'status' => $e->status,
            'has_brief' => (bool) $e->brief_path,
            'created_at' => $e->created_at?->toIso8601String(),
        ];

        return $full ? [
            ...$base,
            'phone' => $e->phone,
            'details' => $e->details,
            'notes' => $e->notes,
            'brief_name' => $e->brief_name,
            'ip_address' => $e->ip_address,
            'user_agent' => $e->user_agent,
            'updated_at' => $e->updated_at?->toIso8601String(),
        ] : $base;
    }

    public static function jobOpening(JobOpening $o): array
    {
        return [
            'id' => $o->id,
            'title' => $o->title,
            'team' => $o->team,
            'type' => $o->type,
            'location' => $o->location,
            'description' => $o->description,
            'status' => $o->status,
            'sort_order' => $o->sort_order,
            'applications_count' => $o->applications_count ?? $o->applications()->count(),
            'updated_at' => $o->updated_at?->toIso8601String(),
        ];
    }

    public static function jobApplication(JobApplication $a, bool $full = false): array
    {
        $base = [
            'id' => $a->id,
            'name' => $a->name,
            'email' => $a->email,
            'job_opening_id' => $a->job_opening_id,
            'role' => $a->opening?->title,
            'status' => $a->status,
            'has_cv' => (bool) $a->cv_path,
            'created_at' => $a->created_at?->toIso8601String(),
        ];

        return $full ? [
            ...$base,
            'phone' => $a->phone,
            'links' => $a->links,
            'message' => $a->message,
            'cv_name' => $a->cv_name,
            'notes' => $a->notes,
            'updated_at' => $a->updated_at?->toIso8601String(),
        ] : $base;
    }

    public static function subscriber(Subscriber $s): array
    {
        return ['id' => $s->id, 'email' => $s->email, 'created_at' => $s->created_at?->toIso8601String()];
    }

    public static function activity(ActivityLog $a): array
    {
        return [
            'id' => $a->id,
            'action' => $a->action,
            'description' => $a->description,
            'user' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
            'ip_address' => $a->ip_address,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }

    /** Paginator → { data, meta } with the given item mapper. */
    public static function page($paginator, callable $map): array
    {
        return [
            'data' => $paginator->getCollection()->map($map)->values(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }
}
