<?php

namespace Modules\Auth\app\Providers;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Modules\Auth\app\Models\User;
use Modules\Auth\app\Policies\UserPolicy;
use Modules\Auth\app\Support\AuthSecurityLogger;
use Nwidart\Modules\Support\ModuleServiceProvider;
use Symfony\Component\HttpFoundation\Response;

class AuthServiceProvider extends ModuleServiceProvider
{
    protected string $name = 'Auth';

    protected string $nameLower = 'auth';

    protected array $providers = [
        EventServiceProvider::class,
        RouteServiceProvider::class,
    ];

    public function boot(): void
    {
        parent::boot();

        Gate::policy(User::class, UserPolicy::class);

        ResetPassword::createUrlUsing(function ($notifiable, string $token): string {
            $base = rtrim((string) (config('auth.password_reset_url') ?: config('app.url')), '/');

            return $base.'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]);
        });

        $this->configureRateLimiting();
    }

    protected function configureRateLimiting(): void
    {
        self::registerRateLimiters();
    }

    /**
     * Register the Authentication rate limiters. Public and static so the
     * definitions can be re-applied to a freshly resolved RateLimiter (for
     * example, after rebinding it onto the array cache store in tests).
     */
    public static function registerRateLimiters(): void
    {
        RateLimiter::for('auth-register', function (Request $request): Limit {
            $by = $request->ip();

            return Limit::perHour(5)->by($by)->response(
                fn ($r, $headers) => self::rateLimitedResponse('auth-register', $r, $headers, $by, 3600, null)
            );
        });

        RateLimiter::for('auth-login-email', function (Request $request): Limit {
            $emailKey = self::emailLimiterKey((string) $request->input('email'));
            $by = $emailKey.'|'.$request->ip();

            return Limit::perMinute(5)->by($by)->response(
                fn ($r, $headers) => self::rateLimitedResponse('auth-login-email', $r, $headers, $by, 60, $request->input('email'))
            );
        });

        RateLimiter::for('auth-login-ip', function (Request $request): Limit {
            $by = $request->ip();

            return Limit::perMinute(30)->by($by)->response(
                fn ($r, $headers) => self::rateLimitedResponse('auth-login-ip', $r, $headers, $by, 60, null)
            );
        });

        RateLimiter::for('auth-forgot-email', function (Request $request): Limit {
            $emailKey = self::emailLimiterKey((string) $request->input('email'));

            return Limit::perHour(3)->by($emailKey)->response(
                fn ($r, $headers) => self::rateLimitedResponse('auth-forgot-email', $r, $headers, $emailKey, 3600, $request->input('email'))
            );
        });

        RateLimiter::for('auth-forgot-ip', function (Request $request): Limit {
            $by = $request->ip();

            return Limit::perHour(10)->by($by)->response(
                fn ($r, $headers) => self::rateLimitedResponse('auth-forgot-ip', $r, $headers, $by, 3600, null)
            );
        });

        RateLimiter::for('auth-reset', function (Request $request): Limit {
            $by = $request->ip();

            return Limit::perMinutes(15, 5)->by($by)->response(
                fn ($r, $headers) => self::rateLimitedResponse('auth-reset', $r, $headers, $by, 900, null)
            );
        });
    }

    /**
     * Build the 429 response identical to the framework default and emit one
     * security event. The response callback only fires when a limit is already
     * exceeded, so logging can never change the limiter threshold or outcome.
     */
    private static function rateLimitedResponse(
        string $limiter,
        Request $request,
        array $headers,
        string $dedupSubject,
        int $decaySeconds,
        mixed $email,
    ): Response {
        try {
            app(AuthSecurityLogger::class)->rateLimitExceeded(
                $limiter,
                $request->route()?->getName() ?? $request->path(),
                $request->method(),
                $request->ip() ?? 'unknown',
                $dedupSubject,
                $decaySeconds,
                is_string($email) && $email !== '' ? $email : null,
            );
        } catch (\Throwable) {
            // Security logging must never alter rate-limit behaviour.
        }

        return response()->json(['message' => 'Too Many Attempts.'], 429)->withHeaders($headers);
    }

    /**
     * Keyed HMAC-SHA256 of the normalized email, keyed with the application
     * key. The raw email never appears in the cache key.
     */
    public static function emailLimiterKey(string $email): string
    {
        return hash_hmac('sha256', strtolower(trim($email)), (string) config('app.key'));
    }
}
