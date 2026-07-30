<?php

declare(strict_types=1);

namespace Modules\Interaction\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
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
            'author_id' => User::factory(),
            'parent_id' => null,
            'content' => fake()->paragraph(),
        ];
    }

    public function forCommentable(Model $commentable): static
    {
        return $this->state([
            'commentable_type' => $commentable->getMorphClass(),
            'commentable_id' => $commentable->getKey(),
        ]);
    }

    public function reply(?Comment $parent = null): static
    {
        return $this->state([
            'parent_id' => $parent?->getKey(),
        ]);
    }
}
