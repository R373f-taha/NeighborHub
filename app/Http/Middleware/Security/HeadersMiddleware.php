<?php

namespace App\Http\Middleware\Security;

use App\Http\Middleware\Security\Config\SecurityConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Str;
/**
 * Security Headers Middleware
 *
 * How it works:
 * 1. Intercepts every outgoing response
 * 2. Adds security headers to protect against common web vulnerabilities
 * 3. Skips CSP in development for easier debugging
 *
 * Protection against:
 * - XSS attacks (Cross-Site Scripting)
 * - Clickjacking (UI Redress attacks)
 * - MIME type sniffing
 * - Data caching of sensitive information
 * - Protocol downgrade attacks
 * - Mixed content attacks
 */
class HeadersMiddleware
{
    /**
     * Handle the request
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Process the request normally
        $response = $next($request);

        // Apply security headers
        $this->applyHeaders($response, $request);

        return $response;
    }

    /**
     * Apply all security headers to response
     *
     * Flow:
     * 1. Get all configured security headers
     * 2. Skip CSP in local environment (easier debugging)
     * 3. Set each header on the response
     * 4. Remove exposing server info
     */
    private function applyHeaders(Response $response, Request $request): void
    {
        if (str_starts_with($request->path(), 'telescope')) {
        return;
    }
        $headers = SecurityConfig::getHeaders();

        foreach ($headers as $name => $value) {
            if ($name === 'Content-Security-Policy') {
                if ($this->isTelescopePath($request)) {
                    $value = "default-src * 'unsafe-inline' 'unsafe-eval' data: blob:;";
                } elseif (app()->environment('local')) {
                    continue;
                }
            }

            $response->headers->set($name, $value);
        }

        $response->headers->remove('Server');
        $response->headers->remove('X-Powered-By');

        if (app()->environment('production')) {
            $response->headers->set('Server', 'Secure-Server');
        }
    }

    private function isTelescopePath(Request $request): bool
    {
        $path = $request->path();
        return Str::is('telescope/*', $path) || Str::is('telescope-api/*', $path);
    }
}
