<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Admin\AuthController as AdminAuth;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Support\Hr;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Employee sign-in, sign-out and setting a password from the emailed link. Separate from admin sign-in. */
class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'max:200'],
            'remember' => ['boolean'],
        ]);

        $key = 'portal-login:'.Str::lower($credentials['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, config('hr.portal_max_attempts'))) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages(['email' => "Too many attempts. Try again in {$seconds} seconds."])->status(429);
        }

        $ok = Auth::guard('employee')->attemptWhen(
            ['email' => $credentials['email'], 'password' => $credentials['password']],
            fn (Employee $employee) => $employee->status === 'active',
            $request->boolean('remember'),
        );

        if (! $ok) {
            RateLimiter::hit($key, 60);
            // Same message for an unknown email, a wrong password or a closed account.
            throw ValidationException::withMessages(['email' => 'These details do not match our records.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();
        $employee = $request->user('employee');
        $employee->forceFill(['last_login_at' => now()])->save();

        return response()->json(['employee' => self::present($employee), 'csrf' => csrf_token()]);
    }

    public function logout(Request $request): JsonResponse
    {
        // Only the employee guard: an admin signed in on the same browser stays signed in.
        Auth::guard('employee')->logout();
        $request->session()->regenerateToken();

        return response()->json(['csrf' => csrf_token()]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['employee' => self::present($request->user('employee')), 'csrf' => csrf_token()]);
    }

    public function forgotPassword(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'string', 'email', 'max:190']]);

        $employee = Employee::where('email', $request->input('email'))->where('status', 'active')->first();
        if ($employee) {
            Password::broker('employees')->sendResetLink(['email' => $employee->email]);
        }

        return response()->json(['message' => 'If that email has portal access, a link is on its way.']);
    }

    /** Sets the password from the emailed link (first set-up or a reset). */
    public function resetPassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:200'],
            'email' => ['required', 'string', 'email', 'max:190'],
            'password' => ['required', 'string', 'confirmed', AdminAuth::passwordRule()],
        ]);

        $status = Password::broker('employees')->reset(
            ['email' => $data['email'], 'password' => $data['password'], 'password_confirmation' => $request->input('password_confirmation'), 'token' => $data['token']],
            function (Employee $employee, string $password) {
                $employee->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
                event(new PasswordReset($employee));
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'This link is invalid or has expired. Ask HR for a new one.']);
        }

        return response()->json(['message' => 'Your password is set. You can sign in now.']);
    }

    /** What the portal needs to know about the signed-in employee. */
    public static function present(Employee $employee): array
    {
        $employee->loadMissing(['department', 'jobTitle']);

        return [
            'id' => $employee->id,
            'code' => $employee->code,
            'name' => $employee->name,
            'email' => $employee->email,
            'job_title' => $employee->jobTitle?->name,
            'department' => $employee->department?->name,
            'shift' => substr((string) $employee->shift_start, 0, 5).'–'.substr((string) $employee->shift_end, 0, 5),
            'leave_balances' => Hr::leaveBalances($employee),
        ];
    }
}
