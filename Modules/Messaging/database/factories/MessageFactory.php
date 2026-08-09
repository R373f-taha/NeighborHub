<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Models\Message;

/**
 * @extends Factory<Message>
 */
class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'conversation_id' => null,
            'sender_id' => null,

            'content' => fake()->sentence(
                fake()->numberBetween(5, 15)
            ),

            'is_read' => false,
            'read_at' => null,
            'community_id' => null,
        ];
    }

    public function forConversation(
        Conversation $conversation
    ): static {
        return $this->state([
            'conversation_id' => $conversation->id,
            'community_id' => $conversation->community_id,
        ]);
    }

    public function fromUser(User $user): static
    {
        return $this->state([
            'sender_id' => $user->id,
        ]);
    }

    public function read(): static
    {
        return $this->state([
            'is_read' => true,
            'read_at' => now(),
        ]);
    }

    public function unread(): static
    {
        return $this->state([
            'is_read' => false,
            'read_at' => null,
        ]);
    }
}