<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Modules\Notification\app\Models\Notification;

/**
 * Inbox read / read-state / delete contract for the Notification API.
 *
 * Notifications are strictly user-private: an actor may only read, mark-read or
 * delete their own notifications. These tests protect that boundary plus the
 * privacy-safe resource serialization.
 */
class NotificationInboxTest extends NotificationTestCase
{
    public function test_anonymous_requests_are_unauthenticated(): void
    {
        $this->getJson('/api/v1/notifications')->assertUnauthorized();
        $this->getJson('/api/v1/notifications/1')->assertUnauthorized();
        $this->putJson('/api/v1/notifications/1/read')->assertUnauthorized();
        $this->deleteJson('/api/v1/notifications/1')->assertUnauthorized();
    }

    public function test_index_returns_only_the_owners_notifications_newest_first(): void
    {
        $first = $this->makeNotification($this->owner, ['title' => 'older']);
        // Force ordering by backdating the first notification.
        Notification::whereKey($first->id)->update(['created_at' => now()->subHour()]);
        $second = $this->makeNotification($this->owner, ['title' => 'newer']);
        $this->makeNotification($this->intruder, ['title' => 'not mine']);

        $response = $this->getJson('/api/v1/notifications', $this->bearer($this->ownerToken))
            ->assertStatus(200);

        $titles = collect($response->json('data'))->pluck('title')->all();

        // The intruder's notification never leaks into the owner's inbox.
        $this->assertNotContains('not mine', $titles);
        $this->assertSame(['newer', 'older'], $titles);
    }

    public function test_show_returns_safe_fields_and_rejects_other_users(): void
    {
        $mine = $this->makeNotification($this->owner);
        $theirs = $this->makeNotification($this->intruder);

        $response = $this->getJson("/api/v1/notifications/{$mine->id}", $this->bearer($this->ownerToken))
            ->assertStatus(200);

        $data = $response->json();
        $this->assertSame($mine->id, $data['id']);
        $this->assertSame('Hello', $data['title']);
        $this->assertFalse($data['is_read']);

        // Privacy: the resource never exposes ownership or account internals.
        $this->assertArrayNotHasKey('user_id', $data);
        $this->assertArrayNotHasKey('email', $data);
        $this->assertArrayNotHasKey('password', $data);
        $this->assertArrayNotHasKey('notifiable_type', $data);

        // Another user cannot read someone else's notification.
        $this->getJson("/api/v1/notifications/{$mine->id}", $this->bearer($this->intruderToken))
            ->assertForbidden();
        $this->getJson("/api/v1/notifications/{$theirs->id}", $this->bearer($this->ownerToken))
            ->assertForbidden();
    }

    public function test_missing_notification_is_not_found(): void
    {
        $missing = Notification::max('id') + 1;

        $this->getJson("/api/v1/notifications/{$missing}", $this->bearer($this->ownerToken))
            ->assertNotFound();
    }

    public function test_mark_read_sets_read_at_and_is_idempotent(): void
    {
        $mine = $this->makeNotification($this->owner);
        $theirs = $this->makeNotification($this->intruder);

        $this->putJson("/api/v1/notifications/{$mine->id}/read", [], $this->bearer($this->ownerToken))
            ->assertStatus(200)
            ->assertJson(['message' => 'Notification marked as read']);

        $this->assertNotNull($mine->fresh()->read_at);

        // Idempotent: marking read a second time still succeeds.
        $this->putJson("/api/v1/notifications/{$mine->id}/read", [], $this->bearer($this->ownerToken))
            ->assertStatus(200);

        // Another user cannot mark someone else's notification read.
        $this->putJson("/api/v1/notifications/{$theirs->id}/read", [], $this->bearer($this->ownerToken))
            ->assertForbidden();
    }

    public function test_destroy_removes_only_owners_notification(): void
    {
        $mine = $this->makeNotification($this->owner);
        $theirs = $this->makeNotification($this->intruder);

        $this->deleteJson("/api/v1/notifications/{$theirs->id}", [], $this->bearer($this->ownerToken))
            ->assertForbidden();
        $this->assertDatabaseHas('notifications', ['id' => $theirs->id]);

        $this->deleteJson("/api/v1/notifications/{$mine->id}", [], $this->bearer($this->ownerToken))
            ->assertStatus(200)
            ->assertJson(['message' => 'Notification deleted successfully']);

        $this->assertDatabaseMissing('notifications', ['id' => $mine->id]);
    }
}
