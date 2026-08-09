<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Modules\Auth\app\Models\User;
use Modules\Notification\app\Models\Notification;
use Tests\AuthApiTestCase;

/**
 * Base fixture for the Notification inbox API.
 *
 * PRODUCTION NOTE: Modules\Notification's NotificationServiceProvider overrides
 * register() without calling parent::register(), so the module's
 * RouteServiceProvider (which loads the inbox routes) is never booted and the
 * inbox endpoints are unreachable in production. To validate the actual inbox
 * contract (owner scoping, read state, privacy-safe resource) we register the
 * module's real routes/api.php here. The route-registration defect is reported
 * separately for an owner fix.
 */
abstract class NotificationTestCase extends AuthApiTestCase
{
    use RefreshDatabase;

    protected User $owner;
    protected User $intruder;
    protected string $ownerToken;
    protected string $intruderToken;

    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        // Load the real module routes file the way production's
        // RouteServiceProvider::map() intends (api + api prefix). The file
        // itself stacks auth:sanctum + v1, yielding api/v1/notifications/*.
        Route::middleware('api')->prefix('api')->group(
            module_path('Notification', 'routes/api.php')
        );
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->resident()->create(['is_active' => true]);
        $this->intruder = User::factory()->resident()->create(['is_active' => true]);

        $this->ownerToken = $this->owner->createToken('notif-test')->plainTextToken;
        $this->intruderToken = $this->intruder->createToken('notif-test')->plainTextToken;
    }

    /** @return array<string, string> */
    protected function bearer(string $token): array
    {
        return ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'];
    }

    protected function makeNotification(User $user, array $overrides = []): Notification
    {
        return Notification::create(array_merge([
            'user_id' => $user->id,
            'title' => 'Hello',
            'body' => 'Body text',
            'type' => 'announcement',
            'data' => ['announcement_id' => 1],
            'notifiable_type' => 'Modules\\Community\\app\\Models\\Announcement',
            'notifiable_id' => 1,
        ], $overrides));
    }
}
