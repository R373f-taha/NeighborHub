<?php

namespace App\Http\Middleware\Security;

use App\Http\Middleware\Security\Config\SecurityConfig;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;


/**
 * CORS Middleware
 *
 * How it works:
 * 1. Checks the 'Origin' header of incoming requests
 * 2. Validates if the origin is in the trusted list
 * 3. If trusted: adds CORS headers to allow cross-origin requests
 * 4. If untrusted: blocks the request with 403
 * 5. Handles preflight OPTIONS requests automatically
 *
 * Why this is important:
 * - Prevents unauthorized domains from accessing your API
 * - Protects against CSRF attacks
 * - Controls what HTTP methods and headers are allowed
 * - Manages credential sharing (cookies, auth tokens)
 */
class CorsMiddleware
{
    /**
     * Handle the request
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Handle preflight OPTIONS request
        if ($request->method() === 'OPTIONS') {
            return $this->handlePreflight($request);
        }

        // Process the request
        $response = $next($request);

        // Apply CORS headers if origin is trusted
        if ($this->isOriginAllowed($request)) {
            $this->applyCorsHeaders($response, $request);
        } else {
            // Block untrusted origins
            Log::warning('🚫 CORS blocked', [
                'origin' => $request->header('Origin'),
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'error' => 'CORS policy: Origin not allowed',
                'message' => 'Your domain is not trusted'
            ], 403);
        }

        return $response;
    }

    /**
     * Check if origin is allowed
     *
     * Logic:
     * 1. If no origin header: treat as same-origin (allowed)
     * 2. In development: allow all origins (easier testing)
     * 3. In production: check against trusted origins list
     * 4. Supports wildcard domains (*.yourdomain.com)
     */
    private function isOriginAllowed(Request $request): bool
    {
        $origin = $request->header('Origin');

        // No origin = same origin = allowed
        if (!$origin) {
            return true;
        }

        // Development: allow all origins
        if (app()->environment('local', 'development')) {
            return true;
        }

        // Production: check trusted origins
        $trusted = SecurityConfig::getTrustedOrigins();

        foreach ($trusted as $trustedOrigin) {
            // Handle wildcard (*.domain.com)
            if (str_contains($trustedOrigin, '*')) {
                $pattern = str_replace('.', '\.', $trustedOrigin);
                $pattern = str_replace('*', '.*', $pattern);
                if (preg_match('/^' . $pattern . '$/', $origin)) {
                    return true;
                }
            } elseif ($origin === $trustedOrigin) {
                return true;
            }
        }

        return false;
    }

    /**
     * Apply CORS headers to response
     */
    private function applyCorsHeaders(Response $response, Request $request): void
    {
        $origin = $request->header('Origin');

        $response->headers->set('Access-Control-Allow-Origin', $origin ?? '*');
        $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS');
        $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-CSRF-TOKEN, Accept, Origin');
        $response->headers->set('Access-Control-Allow-Credentials', 'true');
        $response->headers->set('Access-Control-Max-Age', '86400'); // 24 hours cache
    }

    /**
     * Handle preflight OPTIONS request
     *
     * Browser sends this before actual request to check CORS permissions
     */
    private function handlePreflight(Request $request): Response
    {
        if (!$this->isOriginAllowed($request)) {
            return response('Origin not allowed', 403);
        }

        $response = new Response('', 200);
        $this->applyCorsHeaders($response, $request);
        $response->headers->set('Content-Length', '0');

        return $response;
    }
}
