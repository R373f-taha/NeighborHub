<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Community\app\Models\Community;
use Modules\Messaging\app\Models\Conversation;


/**
 * @extends Factory<Conversation>
 */
class ConversationFactory extends Factory
{
    protected $model = Conversation::class;


    public function definition(): array
    {
        return [

            'community_id' => Community::factory(),

            'type' => fake()->randomElement([
                'direct',
                'group',
                'appeal',
            ]),


            'status' => fake()->randomElement([
                'active',
                'archived',
                'closed',
            ]),
        ];
    }
}