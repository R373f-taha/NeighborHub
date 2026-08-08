<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use Modules\Community\app\Models\Community;
use Modules\Messaging\app\Models\Conversation;
use PDO;
use Tests\TestCase;

/**
 * MSG-1: proves the real MySQL Conversation-row serialization primitive.
 *
 * Future SendMessage / MarkRead transactions will serialize all message
 * inserts and read-cursor advances for one Conversation through that
 * Conversation row (SELECT ... FOR UPDATE). This test demonstrates the DB
 * locking foundation exists: a second FOR UPDATE on a row already locked
 * by another connection blocks until the holder releases it (lock-wait).
 *
 * It does NOT claim the future API is race-safe; it only proves the
 * primitive. Uses two independent raw PDO connections so Laravel's test
 * transaction wrapping does not hide the contention.
 *
 * LOCAL ONLY / NOT staged.
 */
class ConversationRowSerializationPrimitiveTest extends TestCase
{
    // No RefreshDatabase: committed rows are required so the second
    // connection can observe the locked Conversation row.

    public function test_conversation_row_for_update_serializes_concurrent_access(): void
    {
        [$community, $conversation] = $this->seedConversation();

        $holder = $this->pdo();
        $holder->exec('SET SESSION innodb_lock_wait_timeout = 5');
        $holder->beginTransaction();
        $holder->query("SELECT id FROM conversations WHERE id = {$conversation->id} FOR UPDATE");

        $contender = $this->pdo();
        $contender->exec('SET SESSION innodb_lock_wait_timeout = 1');

        $blocked = false;
        $start = microtime(true);

        try {
            $contender->beginTransaction();
            $contender->query("SELECT id FROM conversations WHERE id = {$conversation->id} FOR UPDATE");
        } catch (\Throwable $e) {
            // Lock wait timeout exceeded -> contention was observed.
            $blocked = str_contains($e->getMessage(), 'Lock wait timeout exceeded');
        }

        $elapsed = microtime(true) - $start;

        // Release the held lock and clean up committed rows.
        $holder->commit();
        $this->cleanup($conversation, $community);

        $this->assertTrue(
            $blocked,
            'A second SELECT ... FOR UPDATE on a locked Conversation row must block (lock-wait).',
        );
        $this->assertGreaterThanOrEqual(
            0.5,
            $elapsed,
            'The contender must have actually waited on the row lock.',
        );
    }

    private function seedConversation(): array
    {
        $community = Community::create([
            'name' => 'Serialization Probe',
            'city' => 'Beirut',
            'address' => 'Ring',
        ]);

        $conversation = Conversation::create([
            'community_id' => $community->id,
            'type' => 'direct',
            'status' => 'active',
        ]);

        return [$community, $conversation];
    }

    private function cleanup(Conversation $conversation, Community $community): void
    {
        // conversation_participants / messages cascade off conversations.
        $conversation->delete();
        $community->delete();
    }

    private function pdo(): PDO
    {
        $cfg = config('database.connections.mysql');

        return new PDO(
            "mysql:host={$cfg['host']};port={$cfg['port']};dbname={$cfg['database']}",
            $cfg['username'],
            $cfg['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
        );
    }
}
