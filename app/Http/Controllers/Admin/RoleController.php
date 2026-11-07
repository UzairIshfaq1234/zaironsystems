<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin;
use App\Models\ActivityLog;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    use Guards;

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->hasPermission('roles.view') || $user->hasPermission('users.create') || $user->hasPermission('users.update'), 403);

        $roles = Role::withCount('users')->orderByDesc('is_system')->orderBy('name')->get();

        return response()->json(['data' => $roles->map(fn ($r) => Admin::role($r))]);
    }

    /** The permission catalog, grouped for the role editor. */
    public function permissions(): JsonResponse
    {
        $groups = collect(config('permissions.groups'))->map(fn ($perms, $group) => [
            'group' => $group,
            'permissions' => collect($perms)->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values(),
        ])->values();

        return response()->json(['data' => $groups]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $this->ensureCanGrant($request->user(), $data['permissions']);

        $role = Role::create([...$data, 'slug' => $this->uniqueSlug($data['name']), 'is_system' => false]);
        ActivityLog::record('role.created', "Created role {$role->name}", $role, ['permissions' => $role->permissions]);

        return response()->json(['data' => Admin::role($role)], 201);
    }

    public function update(Request $request, Role $role): JsonResponse
    {
        abort_if($role->is_system, 403, 'System roles cannot be changed.');
        $actor = $request->user();
        $this->ensureCanGrant($actor, $role->permissions ?? []);

        $data = $this->validated($request, $role);
        $this->ensureCanGrant($actor, $data['permissions']);

        $role->update($data);
        ActivityLog::record('role.updated', "Updated role {$role->name}", $role, ['permissions' => $role->permissions]);

        return response()->json(['data' => Admin::role($role)]);
    }

    public function destroy(Request $request, Role $role): JsonResponse
    {
        abort_if($role->is_system, 403, 'System roles cannot be deleted.');
        $this->ensureCanGrant($request->user(), $role->permissions ?? []);

        $role->delete();
        ActivityLog::record('role.deleted', "Deleted role {$role->name}");

        return response()->json(status: 204);
    }

    private function validated(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80', Rule::unique('roles')->ignore($role?->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'distinct', Rule::in(Role::catalog())],
        ]);
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'role';
        $slug = $base;
        for ($i = 2; Role::where('slug', $slug)->exists(); $i++) {
            $slug = "{$base}-{$i}";
        }

        return $slug;
    }
}
