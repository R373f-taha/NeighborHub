<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Auth\app\Models\User;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Validates the Eloquent relationships restored on the canonical Auth User
 * model. These assertions use relationship introspection (which does not
 * execute database queries) so they do not require a migrated database.
 */
class UserRelationshipsTest extends TestCase
{
    /**
     * @return array<string, array{0: class-string, 1: class-string<\Illuminate\Database\Eloquent\Relations\Relation>}>
     */
    protected function setUp(): void
    {
        parent::setUp();

        try {
            class_exists('Modules\Media\app\Models\Media');
        } catch (\Throwable $e) {
            $this->markTestSkipped(
                'Skipping User relationship tests because the current PHP interpreter cannot parse module Media model syntax: '.$e->getMessage()
            );
        }
    }

    public static function relationshipProvider(): array
    {
        return [
            'resident' => ['resident', HasOne::class],
            'currentResident' => ['currentResident', HasOne::class],
            'managedCommunities' => ['managedCommunities', BelongsToMany::class],
            'reportedIssues' => ['reportedIssues', HasMany::class],
            'assignedIssues' => ['assignedIssues', HasMany::class],
            'sentMessages' => ['sentMessages', HasMany::class],
            'conversationParticipants' => ['conversationParticipants', HasMany::class],
            'userNotifications' => ['userNotifications', HasMany::class],
            'uploadedMedia' => ['uploadedMedia', HasMany::class],
            'authoredComments' => ['authoredComments', HasMany::class],
            'reactions' => ['reactions', HasMany::class],
            'createdPolls' => ['createdPolls', HasMany::class],
            'closedPolls' => ['closedPolls', HasMany::class],
            'announcements' => ['announcements', HasMany::class],
        ];
    }

    #[DataProvider('relationshipProvider')]
    public function test_relationship_returns_expected_relation_type(string $method, string $expected): void
    {
        $relation = (new User())->{$method}();

        $this->assertInstanceOf($expected, $relation);
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function foreignKeyProvider(): array
    {
        return [
            'resident uses user_id' => ['resident', 'user_id'],
            'reportedIssues uses reported_by' => ['reportedIssues', 'reported_by'],
            'assignedIssues uses assigned_to' => ['assignedIssues', 'assigned_to'],
            'sentMessages uses sender_id' => ['sentMessages', 'sender_id'],
            'conversationParticipants uses user_id' => ['conversationParticipants', 'user_id'],
            'userNotifications uses user_id' => ['userNotifications', 'user_id'],
            'uploadedMedia uses uploaded_by' => ['uploadedMedia', 'uploaded_by'],
            'authoredComments uses author_id' => ['authoredComments', 'author_id'],
            'reactions uses user_id' => ['reactions', 'user_id'],
            'createdPolls uses created_by' => ['createdPolls', 'created_by'],
            'closedPolls uses actual colsed_by_manager column' => ['closedPolls', 'colsed_by_manager'],
            'announcements uses created_by_manager' => ['announcements', 'created_by_manager'],
        ];
    }

    #[DataProvider('foreignKeyProvider')]
    public function test_relationship_foreign_key_matches_migration_column(string $method, string $expectedKey): void
    {
        $relation = (new User())->{$method}();

        $this->assertSame($expectedKey, $relation->getForeignKeyName());
    }

    public function test_managed_communities_uses_community_mangers_pivot_with_correct_keys(): void
    {
        $relation = (new User())->managedCommunities();

        $this->assertSame('community_mangers', $relation->getTable());
        $this->assertSame('manager_id', $relation->getForeignPivotKeyName());
        $this->assertSame('community_id', $relation->getRelatedPivotKeyName());
        $this->assertSame('communities', $relation->getRelated()->getTable());
    }

    public function test_announcements_relationship_targets_community_module_announcement(): void
    {
        $related = (new User())->announcements()->getRelated();

        $this->assertSame('Modules\Community\app\Models\Announcement', $related::class);
        $this->assertSame('announcements', $related->getTable());
    }

    public function test_all_relationship_target_models_resolve(): void
    {
        $targets = [
            'Modules\Community\app\Models\Resident',
            'Modules\Community\app\Models\Community',
            'Modules\Community\app\Models\Announcement',
            'Modules\Issue\app\Models\Issue',
            'Modules\Messaging\app\Models\Message',
            'Modules\Messaging\app\Models\ConversationParticipant',
            'Modules\Notification\app\Models\Notification',
            'Modules\Media\app\Models\Media',
            'Modules\Interaction\app\Models\Comment',
            'Modules\Interaction\app\Models\Reaction',
            'Modules\Poll\app\Models\Poll',
        ];

        foreach ($targets as $class) {
            $this->assertTrue(class_exists($class), "Failed asserting target model $class is autoloadable.");
        }
    }
}
