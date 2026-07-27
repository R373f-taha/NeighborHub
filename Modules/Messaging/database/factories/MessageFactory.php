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

            'conversation_id' => Conversation::factory(),

            'sender_id' => User::factory(),

            'content' => fake()->sentence(10),

            'is_read' => fake()->boolean(70),

            'read_at' => fake()->boolean(70)
                ? now()
                : null,


            'community_id' => Community::factory(),

        ];
    }
}