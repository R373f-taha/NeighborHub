<?php

namespace App\Http\Middleware\Security\Config;

/**
 * Central Security Configuration
 * All security settings are managed from this single file
 */
class SecurityConfig
{
    /**
     * Get all security headers
     */
    public static function getHeaders(): array
    {
        return [
            // Force HTTPS - protects against protocol downgrade attacks
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains; preload',

            // Prevent clickjacking - stops your site from being loaded in iframes
            'X-Frame-Options' => 'DENY',

            // Prevent MIME type sniffing - stops browsers from guessing content types
            'X-Content-Type-Options' => 'nosniff',

            // XSS Protection - enables browser's built-in XSS filter
            'X-XSS-Protection' => '1; mode=block',

            // Referrer Policy - controls how much referrer info is sent
            'Referrer-Policy' => 'strict-origin-when-cross-origin',

            // Content Security Policy - whitelist where content can load from
            'Content-Security-Policy' => self::getCSP(),

            // Permission Policy - restrict browser features
            'Permissions-Policy' => self::getPermissionsPolicy(),

            // Cache Control - prevent sensitive data from being cached
            'Cache-Control' => 'private, no-store, no-cache, max-age=0, must-revalidate',

            // Prevent caching - old header for compatibility
            'Pragma' => 'no-cache',

            // Clear browsing data - clears cache/cookies on logout
            'Clear-Site-Data' => '"cache", "cookies", "storage"',
        ];
    }

    /**
     * Content Security Policy - Define where resources can load from
     * This prevents XSS attacks by whitelisting allowed sources
     */
    private static function getCSP(): string
    {
        $policy = [
            "default-src 'self'",  // Default: only load from same domain

            // Scripts: allow inline scripts (needed for some JS libraries)
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:",

            // Styles: allow inline styles (needed for CSS frameworks)
            "style-src 'self' 'unsafe-inline' https:",

            // Images: allow from anywhere but block malicious
            "img-src 'self' data: https:",

            // Fonts: only from trusted sources
            "font-src 'self' https:",

            // AJAX/Fetch: only to same domain or trusted APIs
            "connect-src 'self' https:",

            // Prevent framing: block clickjacking
            "frame-ancestors 'none'",

            // Block plugins
            "object-src 'none'",

            // Block base URL changes
            "base-uri 'self'",

            // Only allow forms to submit to same domain
            "form-action 'self'",
        ];

        return implode('; ', $policy);
    }

    /**
     * Permission Policy - Control browser features
     * Prevents unauthorized access to device features
     */
    private static function getPermissionsPolicy(): string
    {
        $features = [
            'geolocation' => '()',      // Block location access
            'microphone' => '()',       // Block microphone access
            'camera' => '()',           // Block camera access
            'payment' => '()',          // Block payment API
            'usb' => '()',              // Block USB access
            'bluetooth' => '()',        // Block Bluetooth access
            'screen-wake-lock' => '()', // Block screen wake lock
            'gamepad' => '()',          // Block gamepad access
            'gyroscope' => '()',        // Block gyroscope access
            'magnetometer' => '()',     // Block magnetometer access
            'accelerometer' => '()',    // Block accelerometer access
        ];

        return implode(', ', array_map(
            fn($feature, $value) => "{$feature}={$value}",
            array_keys($features),
            $features
        ));
    }

    /**
     * Trusted origins for CORS
     */
    public static function getTrustedOrigins(): array
    {
        $origins = [
            'http://localhost:3000',
            'http://localhost:5173',
            'http://127.0.0.1:8000',
        ];

        // Add production domain from .env
        if (env('APP_URL')) {
            $origins[] = env('APP_URL');
        }

        // Add custom origins from .env
        if (env('TRUSTED_ORIGINS')) {
            $custom = explode(',', env('TRUSTED_ORIGINS'));
            $origins = array_merge($origins, $custom);
        }

        return array_unique($origins);
    }

    /**
     * Suspicious headers that should be blocked
     */
    public static function getSuspiciousHeaders(): array
    {
        return [
            'X-Forwarded-Host',      // Header spoofing
            'X-Forwarded-Scheme',    // Protocol spoofing
            'X-Original-URL',        // URL rewriting attacks
            'X-Rewrite-URL',         // URL rewriting attacks
            'X-HTTP-Method-Override', // Method spoofing
            'X-Forwarded-For',       // IP spoofing (handle carefully)
        ];
    }

    /**
     * Blocked user agents (bots/scrapers)
     */
    public static function getBlockedUserAgents(): array
    {
        return [
            'bot', 'crawler', 'spider', 'scraper',
            'curl', 'wget', 'httpie', 'postman',
            'python', 'java', 'perl', 'ruby',
            'nmap', 'sqlmap', 'nikto', 'burp',
        ];
    }
}

