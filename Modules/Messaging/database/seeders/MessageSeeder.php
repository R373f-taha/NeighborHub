<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Models\Message;

class MessageSeeder extends Seeder
{
    private const MIN_MESSAGES = 10;
    private const MAX_MESSAGES = 20;

    public function run(): void
    {
        Conversation::query()
            ->each(function (Conversation $conversation): void {

                $participants = $conversation->participants()->get();

                if ($participants->isEmpty()) {
                    return;
                }

                $existingCount = $conversation->messages()->count();

                $target = rand(
                    self::MIN_MESSAGES,
                    self::MAX_MESSAGES
                );

                $missing = max(0, $target - $existingCount);

                if ($missing === 0) {
                    return;
                }

                for ($i = 0; $i < $missing; $i++) {
                    Message::factory()->create([
                        'conversation_id' => $conversation->id,
                        'sender_id' => $participants->random()->user_id,
                        'community_id' => $conversation->community_id,
                    ]);
                }
            });
    }
}