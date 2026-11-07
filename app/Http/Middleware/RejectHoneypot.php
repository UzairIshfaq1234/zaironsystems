<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forms include a hidden "website" field that people never see. Bots that fill it get a normal
 * looking success response, so they don't learn to adapt, but nothing is stored or sent.
 */
class RejectHoneypot
{
    public const FIELD = 'website';

    public function handle(Request $request, Closure $next): Response
    {
        if (filled($request->input(self::FIELD))) {
            return response()->json(['message' => 'Thanks! We will be in touch.']);
        }

        return $next($request);
    }
}
