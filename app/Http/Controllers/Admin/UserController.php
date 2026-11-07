<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin;
use App\Models\ActivityLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    use Guards;

    public function index(Request $request): JsonResponse
    {
        $request->validate(['q' => ['nullable', 'string', 'max:100']]);

        $page = User::with('roles')
            ->when($request->input('q'), function ($query, string $q) {
                $like = '%'.addcslashes($q, '%_\\').'%';
                $query->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like));
            })
            ->orderBy('name')
            ->paginate(20);

        return response()->json(Admin::page($page, fn ($u) => Admin::user($u)));
    }

    /** Creates the account with an unusable random password and emails an invitation to set one. */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc,strict', 'max:190', 'unique:users,email'],
            'roles' => ['required', 'array', 'min:1', 'max:10'],
            'roles.*' => ['integer', 'distinct', 'exists:roles,id'],
        ]);

        $roles = Role::findMany($data['roles']);
        $this->ensureCanAssign($request->user(), $roles);

        $user = DB::transaction(function () use ($data, $roles) {
            $user = User::create([
                'name' => $data['name'],
                'email' => Str::lower($data['email']),
                'password' => Str::password(48),
                'is_active' => true,
                'invited_at' => now(),
            ]);
            $user->roles()->sync($roles->pluck('id'));

            return $user;
        });

        $this->sendInvite($user, $request->user());
        ActivityLog::record('user.invited', "Invited {$user->name} ({$user->email}) as ".$roles->pluck('name')->join(', '), $user);

        return response()->json(['data' => Admin::user($user->load('roles'))], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        $this->ensureCanManage($actor, $user);

        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:120'],
            'email' => ['sometimes', 'required', 'string', 'email:rfc,strict', 'max:190', Rule::unique('users')->ignore($user->id)],
            'is_active' => ['sometimes', 'boolean'],
            'roles' => ['sometimes', 'array', 'min:1', 'max:10'],
            'roles.*' => ['integer', 'distinct', 'exists:roles,id'],
        ]);

        $isSelf = $actor->is($user);
        if ($isSelf && (isset($data['roles']) || (isset($data['is_active']) && ! $data['is_active']))) {
            throw ValidationException::withMessages(['roles' => "You can't change your own roles or deactivate yourself. Ask another administrator."]);
        }

        if (isset($data['roles'])) {
            $roles = Role::findMany($data['roles']);
            $this->ensureCanAssign($actor, $roles);
            $losesSuperAdmin = $user->isSuperAdmin() && ! $roles->contains(fn (Role $r) => $r->isSuperAdmin());
            if ($losesSuperAdmin && $this->superAdminCount() <= 1) {
                throw ValidationException::withMessages(['roles' => 'This is the last super admin. Make someone else super admin first.']);
            }
        }
        if (isset($data['is_active']) && ! $data['is_active'] && $user->isSuperAdmin() && $this->superAdminCount() <= 1) {
            throw ValidationException::withMessages(['is_active' => "The last super admin can't be deactivated."]);
        }

        DB::transaction(function () use ($user, $data) {
            $user->update(collect($data)->only(['name', 'email', 'is_active'])->all());
            if (isset($data['roles'])) {
                $user->roles()->sync($data['roles']);
            }
        });
        $user->flushPermissionCache();

        ActivityLog::record('user.updated', "Updated {$user->name}", $user, collect($data)->except('name')->all());

        return response()->json(['data' => Admin::user($user->load('roles'))]);
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();
        abort_if($actor->is($user), 422, "You can't delete your own account.");
        $this->ensureCanManage($actor, $user);
        if ($user->isSuperAdmin() && $this->superAdminCount() <= 1) {
            abort(422, "The last super admin can't be deleted.");
        }

        $user->delete();
        ActivityLog::record('user.deleted', "Deleted {$user->name} ({$user->email})");

        return response()->json(status: 204);
    }

    public function resendInvite(Request $request, User $user): JsonResponse
    {
        $this->ensureCanManage($request->user(), $user);
        abort_unless($user->invited_at && ! $user->last_login_at, 422, 'This user has already accepted their invitation.');

        $this->sendInvite($user, $request->user());
        ActivityLog::record('user.invite_resent', "Resent the invitation to {$user->email}", $user);

        return response()->json(['message' => "Invitation resent to {$user->email}."]);
    }

    private function sendInvite(User $user, User $by): void
    {
        $token = Password::broker('invites')->createToken($user);
        $user->notify(new UserInvitation($token, $by->name));
    }
}
