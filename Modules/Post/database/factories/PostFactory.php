<?php

declare(strict_types=1);

namespace Modules\Post\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
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

    /**
     * Link the post to the community it belongs to. Attaches the existing
     * Community rather than creating a new one.
     */
    public function forCommunity(Community $community): static
    {
        return $this->for($community, 'community');
    }

    /**
     * Link the post to the resident who authored it. The Post model names its
     * Resident relationship "author" (resident_id foreign key), so map the
     * domain helper onto that relationship.
     */
    public function forResident(Resident $resident): static
    {
        return $this->for($resident, 'author');
    }
}