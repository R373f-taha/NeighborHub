<?php

declare(strict_types=1);

namespace Modules\Post\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Post\app\Models\Post;

class PostFactory extends Factory
{
    protected $model = Post::class;

    public function definition(): array
    {
        return [
            'category' => fake()->randomElement([
                'general',
                'lost_found',
                'question',
                'event',
                'recommendation',
            ]),
            'content' => fake()->paragraph(3),
            'is_pinned' => null,
            'pinned_by' => null,
        ];
    }

    public function forCommunity(Community $community): static
    {
        return $this->for($community);
    }

    public function forResident(Resident $resident): static
    {
        return $this->for($resident, 'author');
    }
}
