<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Models\User;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Models\ConversationParticipant;

/**
 * @extends Factory<ConversationParticipant>
 */
class ConversationParticipantFactory extends Factory
{
    protected $model = ConversationParticipant::class;

    public function definition(): array
    {
        return [
            'conversation_id' => null,
            'user_id' => null,
            'joined_at' => now(),
            'left_at' => null,
        ];
    }

    public function forConversation(Conversation $conversation): static
    {
        return $this->state([
            'conversation_id' => $conversation->id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state([
            'user_id' => $user->id,
        ]);
    }
}