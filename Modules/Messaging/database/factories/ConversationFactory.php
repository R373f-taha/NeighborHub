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
            // community_id is a NOT NULL FK; provide a valid default so the
            // factory can create a record standalone. forCommunity() overrides
            // this, so seeders are unaffected.
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

    public function forCommunity(Community $community): static
    {
        return $this->state([
            'community_id' => $community->id,
        ]);
    }

    public function active(): static
    {
        return $this->state([
            'status' => 'active',
        ]);
    }
}