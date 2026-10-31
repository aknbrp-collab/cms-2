<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser security headers for every response (admin, public sites, API).
 *
 * No full Content-Security-Policy: Livewire/Alpine need inline scripts and
 * site settings can inject analytics, so a strict policy would break pages.
 * Framing is still blocked through frame-ancestors.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->add([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        ]);

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', "frame-ancestors 'self'; base-uri 'self'; object-src 'none'");
        }

        // Browsers ignore (and warn about) these on plain HTTP.
        if ($request->isSecure()) {
            $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');

            $hstsMaxAge = (int) config('cms.hsts_max_age');

            if ($hstsMaxAge > 0) {
                $response->headers->set('Strict-Transport-Security', "max-age={$hstsMaxAge}");
            }
        }

        return $response;
    }
}
