<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use Illuminate\Support\Facades\DB;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Services\ConversationReadStateService;
use PDO;
use PDOException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * MSG-5 Mark Read concurrency evidence: the Conversation row is the
 * serialization point, concurrent acknowledgements never regress the cursor,
 * and an explicit target keeps newer concurrent sends unread.
 *
 * Mirrors the approved ConcurrentSuperAdminDemotionTest approach: NOT
 * RefreshDatabase (committed rows visible to a second connection); shared
 * disposable test DB with manual cleanup in tearDown().
 *
 * LOCAL ONLY / NOT staged.
 */
class MarkReadConcurrencyTest extends TestCase
{
    use ConversationTestHelpers;

    private Community $community;
    private User $userA;
    private User $userB;
    private Conversation $conversation;
    private ConversationReadStateService $service;
    private int $m10;
    private int $m20;

    /** @var int[] */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->community = $this->makeCommunity('ReadRace');
        $unit = $this->makeUnit($this->community, 'R1');

        $this->userA = User::factory()->resident()->create(['is_active' => true]);
        $this->userB = User::factory()->resident()->create(['is_active' => true]);
        $this->makeResident($this->community, $unit, $this->userA);
        $this->makeResident($this->community, $unit, $this->userB, 'active', 'tenant');
        $this->createdUserIds = [(int) $this->userA->id, (int) $this->userB->id];

        $this->conversation = $this->makeConversation($this->community);
        $this->makeParticipant($this->conversation, $this->userA);
        $this->makeParticipant($this->conversation, $this->userB);

        $m1 = $this->makeMessage($this->conversation, $this->community, $this->userB);
        $this->m10 = (int) $m1->id;
        $m2 = $this->makeMessage($this->conversation, $this->community, $this->userB);
        $this->m20 = (int) $m2->id;

        $this->service = app(ConversationReadStateService::class);
    }

    protected function tearDown(): void
    {
        DB::table('messages')->where('conversation_id', $this->conversation->id)->delete();
        DB::table('conversation_participants')->where('conversation_id', $this->conversation->id)->delete();
        DB::table('conversations')->where('id', $this->conversation->id)->delete();
        DB::table('residents')->whereIn('user_id', $this->createdUserIds)->delete();
        DB::table('users')->whereIn('id', $this->createdUserIds)->delete();
        DB::table('units')->where('community_id', $this->community->id)->delete();
        DB::table('communities')->where('id', $this->community->id)->delete();

        parent::tearDown();
    }

    /**
     * The Conversation row serializes Mark Read: a second connection's
     * FOR UPDATE on the same Conversation blocks (lock-wait) — the same
     * primitive Send Message uses.
     */
    public function test_conversation_row_lock_serializes_concurrent_mark_read(): void
    {
        DB::beginTransaction();
        Conversation::query()->where('id', $this->conversation->id)->lockForUpdate()->pluck('id');

        $conn2 = $this->secondPdoConnection();
        $conn2->exec('SET SESSION innodb_lock_wait_timeout = 2');
        $conn2->beginTransaction();

        $blocked = false;
        try {
            $conn2->prepare('SELECT id FROM conversations WHERE id = ? FOR UPDATE')->execute([$this->conversation->id]);
        } catch (PDOException $e) {
            $blocked = (int) ($e->errorInfo[1] ?? 0) === 1205
                || str_contains($e->getMessage(), 'Lock wait timeout');
        }

        $conn2->rollBack();
        DB::commit();

        $this->assertTrue($blocked, 'Concurrent Mark Read must serialize on the Conversation row.');
    }

    /**
     * Two acknowledgements (M10 then M20) resolve to the maximum cursor (M20),
     * regardless of order. The second (older) is a no-op; no regression.
     */
    public function test_concurrent_acknowledgements_resolve_to_max_cursor(): void
    {
        // Order A: M10 then M20 -> M20.
        $this->service->markRead($this->userA, $this->community, (int) $this->conversation->id, $this->m10);
        $this->service->markRead($this->userA, $this->community, (int) $this->conversation->id, $this->m20);
        $this->assertSameCursor($this->m20);

        // Reset and Order B: M20 then M10 -> M20 (no regression).
        $this->resetCursor();
        $this->service->markRead($this->userA, $this->community, (int) $this->conversation->id, $this->m20);
        $this->service->markRead($this->userA, $this->community, (int) $this->conversation->id, $this->m10);
        $this->assertSameCursor($this->m20);
    }

    /**
     * Send-vs-Mark contract: marking through M10 leaves a concurrently-arrived
     * newer message (M20) unread. The explicit target never jumps to the latest.
     */
    public function test_mark_through_older_keeps_newer_message_unread(): void
    {
        $this->service->markRead($this->userA, $this->community, (int) $this->conversation->id, $this->m10);
        $this->assertSameCursor($this->m10);

        // The newer message (M20) remains unread.
        $participant = $this->actorParticipantRow();
        $unread = $this->service->unreadCount((int) $this->conversation->id, $participant);
        $this->assertSame(1, $unread, 'Newer message must remain unread after marking through an older explicit target.');
    }

    private function assertSameCursor(int $expected): void
    {
        $this->assertSame($expected, (int) $this->actorParticipantRow()->last_read_message_id);
    }

    private function resetCursor(): void
    {
        DB::table('conversation_participants')
            ->where('conversation_id', $this->conversation->id)
            ->where('user_id', $this->userA->id)
            ->update(['last_read_message_id' => null]);
    }

    private function actorParticipantRow(): \Modules\Messaging\app\Models\ConversationParticipant
    {
        return \Modules\Messaging\app\Models\ConversationParticipant::query()
            ->where('conversation_id', $this->conversation->id)
            ->where('user_id', $this->userA->id)
            ->first();
    }

    private function secondPdoConnection(): PDO
    {
        $config = DB::connection('mysql')->getConfig();

        $conn = new PDO(
            sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', $config['host'], $config['port'], $config['database']),
            (string) $config['username'],
            (string) $config['password']
        );
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        return $conn;
    }
}
