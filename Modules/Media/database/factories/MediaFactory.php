<?php

declare(strict_types=1);

namespace Modules\Media\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Models\User;
use Modules\Media\app\Models\Media;

/**
 * @extends Factory<Media>
 */
class MediaFactory extends Factory
{
    protected $model = Media::class;

    /**
     * No filesystem writes and no DB queries: only synthetic metadata. The
     * morph owner is supplied explicitly via the forPost()/forServiceListing()
     * helpers so tests never leave ownership ambiguous.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'file_path' => 'media/' . fake()->uuid() . '.jpg',
            'file_name' => fake()->word() . '.jpg',
            'mime_type' => 'image/jpeg',
            'file_size' => fake()->numberBetween(50_000, 500_000),
            'disk' => 'public',
            'position' => 1,
            'uploaded_by' => User::factory(),
        ];
    }

    public function forPost($post): static
    {
        return $this->for($post, 'mediable');
    }

    public function forServiceListing($listing): static
    {
        return $this->for($listing, 'mediable');
    }

    public function position(int $position): static
    {
        return $this->state(['position' => $position]);
    }
}
