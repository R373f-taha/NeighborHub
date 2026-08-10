<?php

namespace App\Http\Middleware\Security;


use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Http\Middleware\Security\Config\SecurityConfig;

/**
 * Request Validator Middleware
 *
 * How it works:
 * 1. Validates incoming requests for security threats
 * 2. Blocks malicious user agents (bots, scrapers)
 * 3. Blocks suspicious headers (header spoofing)
 * 4. Limits payload size (DDOS protection)
 * 5. Rate limiting protection
 *
 * Why this is important:
 * - Prevents automated attacks from bots
 * - Blocks header injection attacks
 * - Protects against large payload DDOS attacks
 * - Stops reconnaissance tools
 */
class RequestValidatorMiddleware
{

    private array $except = [
        'telescope/*',
        'telescope-api/*',
        'horizon/*',
        'nova-api/*',
        'livewire/*',
        '_debugbar/*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // ✅ إذا كان المسار مستثنى، تخطى التحقق
        if ($this->shouldSkipSecurity($request)) {
            return $next($request);
        }

        // 1. Validate user agent
        if (!$this->isValidUserAgent($request)) {
            Log::warning('🤖 Malicious bot blocked', [
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);
            return $this->blockRequest('Malicious bot detected');
        }

        // 2. Validate headers
        if (!$this->areHeadersValid($request)) {
            Log::warning('🚨 Suspicious headers detected', [
                'headers' => $this->getSuspiciousHeaders($request),
                'ip' => $request->ip(),
            ]);
            return $this->blockRequest('Suspicious headers detected');
        }

        // 3. Validate payload size
        if (!$this->isPayloadValid($request)) {
            Log::warning('🚨 Payload too large', [
                'size' => $request->getContentLength(),
                'ip' => $request->ip(),
            ]);
            return $this->blockRequest('Request payload too large');
        }

        // 4. Validate request method
        if (!$this->isMethodValid($request)) {
            return $this->blockRequest('Invalid request method');
        }

        return $next($request);
    }

   private function shouldSkipSecurity(Request $request): bool
    {
        $path = $request->path();

        foreach ($this->except as $except) {
            // دعم Wildcard (*)
            if (str_contains($except, '*')) {
                // ✅ استخدم preg_quote() لتجاهل الأحرف الخاصة
                $pattern = str_replace('\*', '.*', preg_quote($except, '/'));
                if (preg_match('/^' . $pattern . '$/', $path)) {
                    return true;
                }
            } elseif (str_starts_with($path, $except)) {
                return true;
            } elseif ($path === $except) {
                return true;
            }
        }

        return false;
    }


    /**
     * Validate user agent
     *
     * Logic:
     * - In production: block known bots and scrapers
     * - Allow Googlebot (SEO important)
     * - Block tools like curl, wget, postman
     * - Block vulnerability scanners (nmap, sqlmap, nikto)
     */
    private function isValidUserAgent(Request $request): bool
    {
        // Skip validation in local (easier testing)
        if (app()->environment('local', 'development')) {
            return true;
        }

        $userAgent = strtolower($request->userAgent() ?? '');

        // Allow Googlebot for SEO
        if (str_contains($userAgent, 'googlebot')) {
            return true;
        }

        $blocked = SecurityConfig::getBlockedUserAgents();

        foreach ($blocked as $agent) {
            if (str_contains($userAgent, $agent)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate request headers
     *
     * Blocks headers commonly used in:
     - Header injection attacks
     - IP spoofing
     - Protocol downgrade attacks
     - Cache poisoning
     */
    private function areHeadersValid(Request $request): bool
    {
        $suspicious = SecurityConfig::getSuspiciousHeaders();

        foreach ($suspicious as $header) {
            if ($request->header($header)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate payload size
     *
     * Prevents:
     * - DDOS attacks via large payloads
     * - Memory exhaustion attacks
     * - Disk space exhaustion
     */


    private function isPayloadValid(Request $request): bool
{
    // Skip GET requests (no payload)
    if ($request->method() === 'GET' || $request->method() === 'HEAD') {
        return true;
    }

    $maxSize = env('MAX_PAYLOAD_SIZE', 10 * 1024 * 1024); // 10MB default

    // Get Content-Length from HTTP header
    $contentLength = $request->header('Content-Length');

    // Check Content-Length header
    if ($contentLength !== null && (int) $contentLength > $maxSize) {
        return false;
    }

    // Check actual content size
    $content = $request->getContent();

    if (strlen($content) > $maxSize) {
        return false;
    }

    return true;
}
    // private function isPayloadValid(Request $request): bool
    // {
    //     // Skip GET requests (no payload)
    //     if ($request->method() === 'GET' || $request->method() === 'HEAD') {
    //         return true;
    //     }

    //     $maxSize = env('MAX_PAYLOAD_SIZE', 10 * 1024 * 1024); // 10MB default
    //     $contentLength = $request->getContentLength();

    //     // Check Content-Length header
    //     if ($contentLength && $contentLength > $maxSize) {
    //         return false;
    //     }

    //     // Check actual content size (more reliable)
    //     $content = $request->getContent();
    //     if (strlen($content) > $maxSize) {
    //         return false;
    //     }

    //     return true;
    // }

    /**
     * Validate HTTP method
     */
    private function isMethodValid(Request $request): bool
    {
        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS', 'HEAD'];

        return in_array($request->method(), $allowedMethods);
    }

    /**
     * Get all suspicious headers from request
     */
    private function getSuspiciousHeaders(Request $request): array
    {
        $suspicious = [];
        $headers = SecurityConfig::getSuspiciousHeaders();

        foreach ($headers as $header) {
            if ($request->header($header)) {
                $suspicious[$header] = $request->header($header);
            }
        }

        return $suspicious;
    }

    /**
     * Block request with error response
     */
    private function blockRequest(string $reason): Response
    {
        return response()->json([
            'error' => 'Request blocked',
            'message' => $reason,
            'timestamp' => now()->toDateTimeString(),
        ], 403);
    }
}
