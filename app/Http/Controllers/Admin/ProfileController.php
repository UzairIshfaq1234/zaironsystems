<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin;
use App\Models\ActivityLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc,strict', 'max:190', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($data);
        ActivityLog::record('profile.updated', "{$user->name} updated their profile", $user);

        return response()->json(['user' => Admin::me($user)]);
    }

    public function password(Request $request): JsonResponse
    {
        $request->validate([
            'current_password' => ['required', 'string', 'current_password'],
            'password' => ['required', 'string', 'confirmed', 'different:current_password', AuthController::passwordRule()],
        ]);

        $user = $request->user();
        $user->update(['password' => $request->input('password')]);

        // Sign out every other device that still has the old password.
        Auth::logoutOtherDevices($request->input('password'));
        ActivityLog::record('profile.password', "{$user->name} changed their password", $user);

        return response()->json(['message' => 'Password updated. Other devices have been signed out.']);
    }
}
