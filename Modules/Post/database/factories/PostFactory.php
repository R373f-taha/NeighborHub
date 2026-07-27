<?php

declare(strict_types=1);

namespace Modules\Post\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Post\app\Models\Post;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Community;
class PostFactory extends Factory
{
    protected $model = Post::class;


    public function definition(): array
{
    return [
        'community_id' => Community::factory(),

        'resident_id' => Resident::factory(),

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
}