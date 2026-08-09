<?php

declare(strict_types=1);

namespace Modules\ServiceListing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\ServiceListing\app\Models\ServiceListing;

class ServiceListingFactory extends Factory
{
    protected $model = ServiceListing::class;

    public function definition(): array
    {
        return [
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(3),
            'type' => fake()->randomElement([
                'sale',
                'rent',
                'share',
                'request',
            ]),
            'price' => fake()->randomFloat(2, 10, 1000),
            'status' => 'active',
            'expires_at' => now()->addDays(fake()->numberBetween(7, 30)),
            'closed_at' => null,
        ];
    }
}