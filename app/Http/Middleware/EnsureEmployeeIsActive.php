<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** An employee who has left (or whose record was closed) is signed out on their next request. */
class EnsureEmployeeIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user('employee') && $request->user('employee')->status !== 'active') {
            Auth::guard('employee')->logout();

            return response()->json(['message' => 'Your portal access has ended.'], 401);
        }

        return $next($request);
    }
}
