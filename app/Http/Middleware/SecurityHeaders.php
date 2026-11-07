<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Strict browser security headers on every response.
 * Scripts only run from this origin with a per-request nonce; nothing may frame the site.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        Vite::useCspNonce();

        $response = $next($request);

        $headers = [
            'Content-Security-Policy' => $this->contentSecurityPolicy(Vite::cspNonce()),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=(), browsing-topics=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Resource-Policy' => 'same-origin',
        ];

        if ($request->isSecure() && app()->isProduction()) {
            $headers['Strict-Transport-Security'] = 'max-age=63072000; includeSubDomains';
        }

        $response->headers->add($headers);
        $response->headers->remove('X-Powered-By');
        header_remove('X-Powered-By');

        return $response;
    }

    private function contentSecurityPolicy(string $nonce): string
    {
        $policy = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'"],
            'style-src' => ["'self'"],
            'img-src' => ["'self'", 'data:'],
            'font-src' => ["'self'"],
            'connect-src' => ["'self'"],
            'frame-src' => config('zairon.csp.frame-src') ?: ["'none'"],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        // `npm run dev`: allow the Vite dev server, its HMR socket and its injected <style> tags.
        if (Vite::isRunningHot()) {
            $dev = trim(File::get(Vite::hotFile()));
            $socket = preg_replace('#^http#', 'ws', $dev);
            foreach (['script-src', 'style-src', 'img-src', 'font-src'] as $directive) {
                $policy[$directive][] = $dev;
            }
            $policy['style-src'][] = "'unsafe-inline'";
            array_push($policy['connect-src'], $dev, $socket);
        }

        $directives = array_map(fn ($sources, $name) => $name.' '.implode(' ', $sources), $policy, array_keys($policy));

        if (app()->isProduction()) {
            $directives[] = 'upgrade-insecure-requests';
        }

        return implode('; ', $directives);
    }
}
