<?php

declare(strict_types=1);

namespace Modules\Interaction\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Models\User;
use Modules\Interaction\app\Models\Comment;

/**
 * @extends Factory<Comment>
 */
class CommentFactory extends Factory
{
    protected $model = Comment::class;

public function definition(): array
{
    return [
        'commentable_type' => null,

        'commentable_id' => null,

        'author_id' => User::query()
            ->inRandomOrder()
            ->value('id'),

        'parent_id' => null,

        'content' => fake()->paragraph(),
    ];
}


    public function reply(): static
    {
        return $this->state(fn () => [
            'parent_id' => Comment::factory(),
        ]);
    }
}