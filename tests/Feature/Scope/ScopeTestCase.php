<?php

declare(strict_types=1);

namespace Tests\Feature\Scope;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Auth\app\Models\User;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase as ProjectTestCase;

/**
 * Base for the scoped Spatie / Role Assignment / message tests.
 *
 * Uses the project's standard disposable test database (now that our migration
 * guard is fixed) with per-test transaction isolation, plus a Spatie permission
 * cache flush so role/permission checks are never stale across tests.
 *
 * NOT committed / NOT staged — local validation only.
 */
abstract class ScopeTestCase extends ProjectTestCase
{
    use RefreshDatabase;

    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        if ($this->app && $this->app->bound('auth')) {
            $this->app['auth']->forgetGuards();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @return array<string, string>
     */
    protected function token(User $user): array
    {
        $token = $user->createToken('scope-test')->plainTextToken;

        return ['Authorization' => "Bearer {$token}", 'Accept' => 'application/json'];
    }
}
