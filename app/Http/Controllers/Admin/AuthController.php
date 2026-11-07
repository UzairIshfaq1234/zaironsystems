<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\Admin;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public const MAX_ATTEMPTS = 5;

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
            'remember' => ['boolean'],
        ]);

        $key = 'admin-login:'.Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['email' => "Too many attempts. Try again in {$seconds} seconds."])->status(429);
        }

        $ok = Auth::attemptWhen(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            fn (User $user) => $user->is_active,
            $request->boolean('remember'),
        );

        if (! $ok) {
            RateLimiter::hit($key, 60);
            ActivityLog::record('auth.failed', 'Failed sign-in for '.Str::limit($credentials['email'], 80));
            // Same message whether the email exists, the password is wrong or the account is disabled.
            throw ValidationException::withMessages(['email' => 'These credentials do not match our records.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        ActivityLog::record('auth.login', "{$user->name} signed in", $user);

        return response()->json(['user' => Admin::me($user), 'csrf' => csrf_token()]);
    }

    public function logout(Request $request): JsonResponse
    {
        ActivityLog::record('auth.logout', "{$request->user()->name} signed out", $request->user());

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['csrf' => csrf_token()]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => Admin::me($request->user()), 'csrf' => csrf_token()]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:190']]);

        $user = User::where('email', $request->input('email'))->where('is_active', true)->first();
        if ($user) {
            Password::broker('users')->sendResetLink(['email' => $user->email]);
        }

        // Identical response either way, so the form can't reveal which emails have accounts.
        return response()->json(['message' => 'If that email belongs to an account, a reset link is on its way.']);
    }

    /** Completes both password resets and invitations (?invite=1 uses the invitation token table). */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:200'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'confirmed', self::passwordRule()],
            'invite' => ['boolean'],
        ]);

        $broker = $request->boolean('invite') ? 'invites' : 'users';
        $status = Password::broker($broker)->reset(
            ['email' => $data['email'], 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (User $user, string $password) use ($broker) {
                $user->forceFill(['password' => $password, 'remember_token' => Str::random(60), 'email_verified_at' => $user->email_verified_at ?? now()])->save();
                event(new PasswordReset($user));
                ActivityLog::record($broker === 'invites' ? 'auth.invite_accepted' : 'auth.password_reset', "{$user->name} ".($broker === 'invites' ? 'accepted their invitation' : 'reset their password'), $user);
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This link is invalid or has expired. Ask for a new one.']);
        }

        return response()->json(['message' => 'Your password has been set. You can sign in now.']);
    }

    public static function passwordRule(): PasswordRule
    {
        return PasswordRule::min(10)->letters()->mixedCase()->numbers();
    }
}
