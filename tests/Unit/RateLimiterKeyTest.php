<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Auth\app\Providers\AuthServiceProvider;
use Tests\TestCase;

class RateLimiterKeyTest extends TestCase
{
    private function withKey(string $key): void
    {
        config(['app.key' => $key]);
    }

    public function test_same_normalized_email_produces_the_same_key(): void
    {
        $this->withKey('base64:'.base64_encode('test-application-key'));

        $first = AuthServiceProvider::emailLimiterKey('resident@example.com');
        $second = AuthServiceProvider::emailLimiterKey('resident@example.com');

        $this->assertSame($first, $second);
        $this->assertSame(64, strlen($first));
    }

    public function test_case_and_surrounding_whitespace_normalize_consistently(): void
    {
        $this->withKey('base64:'.base64_encode('test-application-key'));

        $canonical = AuthServiceProvider::emailLimiterKey('resident@example.com');
        $upper = AuthServiceProvider::emailLimiterKey('RESIDENT@example.com');
        $padded = AuthServiceProvider::emailLimiterKey("  \t RESIDENT@Example.COM \n");

        $this->assertSame($canonical, $upper);
        $this->assertSame($canonical, $padded);
    }

    public function test_different_emails_produce_different_keys(): void
    {
        $this->withKey('base64:'.base64_encode('test-application-key'));

        $this->assertNotSame(
            AuthServiceProvider::emailLimiterKey('resident@example.com'),
            AuthServiceProvider::emailLimiterKey('manager@example.com'),
        );
    }

    public function test_raw_email_does_not_appear_in_the_key(): void
    {
        $this->withKey('base64:'.base64_encode('test-application-key'));

        $email = 'resident@example.com';
        $key = AuthServiceProvider::emailLimiterKey($email);

        $this->assertStringNotContainsString($email, $key);
        $this->assertStringNotContainsString(strtolower($email), $key);
    }

    public function test_changing_the_application_key_changes_the_output(): void
    {
        $this->withKey('base64:'.base64_encode('first-application-key'));
        $first = AuthServiceProvider::emailLimiterKey('resident@example.com');

        $this->withKey('base64:'.base64_encode('second-application-key'));
        $second = AuthServiceProvider::emailLimiterKey('resident@example.com');

        $this->assertNotSame($first, $second);
    }
}
