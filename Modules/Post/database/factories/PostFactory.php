<?php

declare(strict_types=1);

namespace Modules\Post\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Post\app\Models\Post;

/**
 * @extends Factory<Post>
 */
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
            'content' => fake()->paragraphs(3, true),
            'is_pinned' => null,
            'pinned_by' => null,
        ];
    }
}