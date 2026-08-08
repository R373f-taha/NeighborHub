<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use Illuminate\Support\Facades\DB;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Messaging\app\Models\Conversation;
use PDO;
use PDOException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Core Send Message concurrency: the Conversation row is the serialization
 * point shared by Send and Mark Read. A second connection's FOR UPDATE on the
 * same Conversation must block (lock-wait) while the first transaction holds
 * it, which is what makes in-conversation send ordering deterministic and the
 * per-participant read cursor reliable.
 *
 * Mirrors the approved ConcurrentSuperAdminDemotionTest approach: NOT
 * RefreshDatabase (committed rows must be visible to a second connection);
 * shared disposable test DB with manual cleanup in tearDown().
 *
 * LOCAL ONLY / NOT staged.
 */
class SendMessageConcurrencyTest extends TestCase
{
    use ConversationTestHelpers;

    private Community $community;
    private User $userA;
    private User $userB;
    private Conversation $conversation;

    /** @var int[] */
    private array $createdUserIds = [];

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->community = $this->makeCommunity('SendRace');
        $unit = $this->makeUnit($this->community, 'S1');

        $this->userA = User::factory()->resident()->create(['is_active' => true]);
        $this->userB = User::factory()->resident()->create(['is_active' => true]);
        $this->makeResident($this->community, $unit, $this->userA);
        $this->makeResident($this->community, $unit, $this->userB, 'active', 'tenant');

        $this->createdUserIds = [(int) $this->userA->id, (int) $this->userB->id];

        $this->conversation = $this->makeConversation($this->community);
        $this->makeParticipant($this->conversation, $this->userA);
        $this->makeParticipant($this->conversation, $this->userB);
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
     * The Conversation row is the serialization point: a second connection's
     * FOR UPDATE on the same Conversation must block (lock-wait) while the
     * first transaction holds it. This makes in-conversation send ordering
     * deterministic.
     */
    public function test_conversation_row_lock_serializes_concurrent_sends(): void
    {
        DB::beginTransaction();

        $locked = Conversation::query()
            ->where('id', $this->conversation->id)
            ->lockForUpdate()
            ->pluck('id')
            ->all();

        $this->assertSame([$this->conversation->id], $locked);

        $conn2 = $this->secondPdoConnection();
        $conn2->exec('SET SESSION innodb_lock_wait_timeout = 2');
        $conn2->beginTransaction();

        $blocked = false;
        try {
            $stmt = $conn2->prepare('SELECT id FROM conversations WHERE id = ? FOR UPDATE');
            $stmt->execute([$this->conversation->id]);
        } catch (PDOException $e) {
            $blocked = (int) ($e->errorInfo[1] ?? 0) === 1205
                || str_contains($e->getMessage(), 'Lock wait timeout');
        }

        $conn2->rollBack();
        DB::commit();

        $this->assertTrue($blocked, 'Concurrent send must serialize on the Conversation row.');
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
