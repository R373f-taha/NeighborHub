<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\Notification\app\Jobs\SendNotificationJob;
use Modules\Notification\app\Models\Notification;

/**
 * Notification generation boundary.
 *
 * SendNotificationJob fans a database notification out to every resident of the
 * announcement's community. This protects the recipient set (community members
 * only), the absence of duplicates, and the payload. The job is dispatched here
 * directly: production additionally fails to register Community's
 * EventServiceProvider, so the AnnouncementCreated -> SendNotificationJob
 * listener wiring is itself inactive and is reported separately.
 */
class NotificationGenerationTest extends NotificationTestCase
{
    public function test_announcement_creation_notifies_each_community_resident(): void
    {
        [$communityA, $residentUserA1, $residentUserA2, $residentUserB] = $this->seedCommunities();

        $announcement = Announcement::create([
            'community_id' => $communityA->id,
            'created_by_manager' => $this->owner->id,
            'title' => 'Water shutdown tomorrow',
            'content' => 'Details...',
            'priority' => 'normal',
        ]);

        SendNotificationJob::dispatchSync($announcement);

        // Exactly two notifications were produced, one per community A resident.
        $this->assertSame(2, Notification::count());

        $notified = Notification::pluck('user_id')->sort()->values()->all();
        $expected = [$residentUserA1->id, $residentUserA2->id];
        sort($expected);
        $this->assertSame(array_values($expected), $notified);

        // The community B resident is never notified.
        $this->assertSame(0, Notification::where('user_id', $residentUserB->id)->count());

        // Payload correctness for a single notification.
        $first = Notification::first();
        $this->assertSame('announcement', $first->type);
        $this->assertSame('New Announcement', $first->title);
        $this->assertSame('Water shutdown tomorrow', $first->body);
        $this->assertSame(['announcement_id' => $announcement->id], $first->data);
    }

    public function test_announcement_with_no_eligible_residents_creates_nothing(): void
    {
        $community = Community::create([
            'name' => 'Empty', 'city' => 'C', 'address' => 'A',
            'total_units' => 1, 'is_active' => true,
        ]);

        $announcement = Announcement::create([
            'community_id' => $community->id,
            'created_by_manager' => $this->owner->id,
            'title' => 'Nobody here',
            'content' => 'd',
            'priority' => 'normal',
        ]);

        SendNotificationJob::dispatchSync($announcement);

        $this->assertSame(0, Notification::count());
    }

    /**
     * @return array{\Modules\Community\app\Models\Community, \Modules\Auth\app\Models\User, \Modules\Auth\app\Models\User, \Modules\Auth\app\Models\User}
     */
    private function seedCommunities(): array
    {
        $communityA = Community::create([
            'name' => 'A', 'city' => 'Amman', 'address' => 'A',
            'total_units' => 10, 'is_active' => true,
        ]);
        $communityB = Community::create([
            'name' => 'B', 'city' => 'Irbid', 'address' => 'B',
            'total_units' => 5, 'is_active' => true,
        ]);

        $unitA1 = Unit::create(['community_id' => $communityA->id, 'unit_number' => 'A1', 'building_name' => 'B', 'rooms' => 2, 'unit_type' => 'apartment', 'is_active' => true]);
        $unitA2 = Unit::create(['community_id' => $communityA->id, 'unit_number' => 'A2', 'building_name' => 'B', 'rooms' => 2, 'unit_type' => 'apartment', 'is_active' => true]);
        $unitB1 = Unit::create(['community_id' => $communityB->id, 'unit_number' => 'B1', 'building_name' => 'B', 'rooms' => 2, 'unit_type' => 'apartment', 'is_active' => true]);

        $residentUserA1 = $this->owner; // owner is a resident of community A
        Resident::create(['user_id' => $residentUserA1->id, 'unit_id' => $unitA1->id, 'community_id' => $communityA->id, 'residence_type' => 'owner', 'status' => 'active', 'current_marker' => true]);

        $residentUserA2 = $this->intruder;
        Resident::create(['user_id' => $residentUserA2->id, 'unit_id' => $unitA2->id, 'community_id' => $communityA->id, 'residence_type' => 'tenant', 'status' => 'active', 'current_marker' => true]);

        $residentUserB = \Modules\Auth\app\Models\User::factory()->resident()->create(['is_active' => true]);
        Resident::create(['user_id' => $residentUserB->id, 'unit_id' => $unitB1->id, 'community_id' => $communityB->id, 'residence_type' => 'owner', 'status' => 'active', 'current_marker' => true]);

        return [$communityA, $residentUserA1, $residentUserA2, $residentUserB];
    }
}
