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


    public function definition(): array
    {
        return [

            'mediable_type' => null,

            'mediable_id' => null,


            'uploaded_by' => User::factory(),


            'file_path' => 'uploads/media/' . fake()->uuid() . '.jpg',

            'file_name' => fake()->word() . '.jpg',

            'mime_type' => 'image/jpeg',

            'file_size' => fake()->numberBetween(
                50000,
                5000000
            ),

            'disk' => 'public',
        ];
    }
}