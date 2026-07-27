<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Models\User;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * Set explicitly because Laravel's default guess resolves to the App
     * namespace, which is not used by this module.
     *
     * @var class-string<User>
     */
    protected $model = User::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            // Stored as plaintext here; the User model's "hashed" cast hashes
            // it on assignment so it is never double-hashed.
            'password' => 'password',
            'role' => UserRole::Resident,
            'is_active' => true,
            'email_verified_at' => now(),
        ];
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes): array => [
            'email_verified_at' => null,
        ]);
    }
}
