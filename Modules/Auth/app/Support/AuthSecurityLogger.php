<?php

declare(strict_types=1);

namespace Modules\Auth\app\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Modules\Auth\app\Models\User;

class AuthSecurityLogger
{
    private const CHANNEL = 'security';

    private const MAX_USER_AGENT_LENGTH = 255;

    private const MAX_TOKEN_NAME_LENGTH = 64;

    public function registrationSucceeded(User $user, AuthSecurityContext $context, string $tokenName): void
    {
        $this->write('auth.registration.succeeded', 'info', [
            'result' => 'success',
            'user_id' => $user->getAuthIdentifier(),
            'email_fingerprint' => $this->emailFingerprint($user->email),
            'ip' => $context->ip ?? 'unknown',
            'user_agent' => $this->sanitizeUserAgent($context->userAgent),
            'token_name' => $this->sanitizeTokenName($tokenName),
        ]);
    }

    public function loginSucceeded(User $user, AuthSecurityContext $context, string $tokenName): void
    {
        $this->write('auth.login.succeeded', 'info', [
            'result' => 'success',
            'user_id' => $user->getAuthIdentifier(),
            'email_fingerprint' => $this->emailFingerprint($user->email),
            'ip' => $context->ip ?? 'unknown',
            'user_agent' => $this->sanitizeUserAgent($context->userAgent),
            'token_name' => $this->sanitizeTokenName($tokenName),
        ]);
    }

    public function loginFailed(string $email, AuthSecurityContext $context): void
    {
        $this->write('auth.login.failed', 'warning', [
            'result' => 'invalid_credentials',
            'email_fingerprint' => $this->emailFingerprint($email),
            'ip' => $context->ip ?? 'unknown',
            'user_agent' => $this->sanitizeUserAgent($context->userAgent),
        ]);
    }

    public function loginBlockedInactive(User $user, AuthSecurityContext $context): void
    {
        $this->write('auth.login.blocked_inactive', 'warning', [
            'result' => 'blocked',
            'user_id' => $user->getAuthIdentifier(),
            'email_fingerprint' => $this->emailFingerprint($user->email),
            'ip' => $context->ip ?? 'unknown',
            'user_agent' => $this->sanitizeUserAgent($context->userAgent),
        ]);
    }

    public function logoutSucceeded(User $user, AuthSecurityContext $context, ?string $tokenName): void
    {
        $this->write('auth.logout.succeeded', 'info', [
            'result' => 'success',
            'user_id' => $user->getAuthIdentifier(),
            'ip' => $context->ip ?? 'unknown',
            'user_agent' => $this->sanitizeUserAgent($context->userAgent),
            'token_name' => $this->sanitizeTokenName($tokenName),
            'revoked_token_count' => 1,
        ]);
    }

    public function passwordChanged(User $user, AuthSecurityContext $context, int $revokedTokenCount): void
    {
        $this->write('auth.password.changed', 'warning', [
            'result' => 'success',
            'user_id' => $user->getAuthIdentifier(),
            'ip' => $context->ip ?? 'unknown',
            'user_agent' => $this->sanitizeUserAgent($context->userAgent),
            'revoked_token_count' => $revokedTokenCount,
        ]);
    }

    public function passwordResetRequested(string $email, AuthSecurityContext $context): void
    {
        $this->write('auth.password_reset.requested', 'info', [
            'result' => 'accepted',
            'email_fingerprint' => $this->emailFingerprint($email),
            'ip' => $context->ip ?? 'unknown',
            'user_agent' => $this->sanitizeUserAgent($context->userAgent),
        ]);
    }

    public function passwordResetCompleted(User $user, AuthSecurityContext $context, int $revokedTokenCount): void
    {
        $this->write('auth.password_reset.completed', 'warning', [
            'result' => 'success',
            'user_id' => $user->getAuthIdentifier(),
            'email_fingerprint' => $this->emailFingerprint($user->email),
            'ip' => $context->ip ?? 'unknown',
            'user_agent' => $this->sanitizeUserAgent($context->userAgent),
            'revoked_token_count' => $revokedTokenCount,
        ]);
    }

    /**
     * Emit one rate-limit event per limiter subject per active window. The
     * dedup subject is the limiter's own keyed identifier (never a raw email),
     * so repeated blocked retries within one window produce a single entry.
     */
    public function rateLimitExceeded(
        string $limiter,
        string $route,
        string $httpMethod,
        string $ip,
        string $dedupSubject,
        int $decaySeconds,
        ?string $email = null,
    ): void {
        $dedupKey = 'auth:seclog:rl:'.$limiter.':'.$dedupSubject;

        try {
            $store = config('auth.security_log_dedup_store', 'redis');
            if (! Cache::store($store)->add($dedupKey, true, $decaySeconds)) {
                return;
            }
        } catch (\Throwable) {
            // Cache failure must never change rate-limit behaviour; the request
            // is already blocked. Log once and continue without dedup.
        }

        $context = [
            'result' => 'blocked',
            'limiter' => $limiter,
            'route' => $route,
            'http_method' => $httpMethod,
            'ip' => $ip,
        ];

        $fingerprint = $this->emailFingerprint($email);
        if ($fingerprint !== null) {
            $context['email_fingerprint'] = $fingerprint;
        }

        $this->write('auth.rate_limit.exceeded', 'warning', $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function write(string $event, string $level, array $context): void
    {
        Log::channel(self::CHANNEL)->log($level, $event, array_merge(['event' => $event], $context));
    }

    private function emailFingerprint(?string $email): ?string
    {
        if ($email === null || trim($email) === '') {
            return null;
        }

        return hash_hmac('sha256', strtolower(trim($email)), (string) config('app.key'));
    }

    private function sanitizeUserAgent(?string $userAgent): ?string
    {
        if (! is_string($userAgent) || $userAgent === '') {
            return null;
        }

        return $this->truncate($userAgent, self::MAX_USER_AGENT_LENGTH);
    }

    private function sanitizeTokenName(?string $name): ?string
    {
        if (! is_string($name) || $name === '') {
            return null;
        }

        return $this->truncate($name, self::MAX_TOKEN_NAME_LENGTH);
    }

    private function truncate(string $value, int $length): ?string
    {
        $sanitized = preg_replace('/[\x00-\x1F\x7F]/', '', $value);

        if (! is_string($sanitized) || $sanitized === '') {
            return null;
        }

        return substr($sanitized, 0, $length);
    }
}
