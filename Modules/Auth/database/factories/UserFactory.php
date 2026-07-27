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

            // سيتم تشفيرها تلقائياً بواسطة cast في الـ Model
            'password' => 'password',

            'role' => fake()->randomElement(UserRole::cases()),

            'phone' => fake()->phoneNumber(),

            'avatar' => fake()->imageUrl(300, 300, 'people'),

            'is_active' => fake()->boolean(90),

            'email_verified_at' => now(),
        ];
    }

    public function superAdmin(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::SuperAdmin,
        ]);
    }

    public function manager(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Manager,
        ]);
    }

    public function resident(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Resident,
        ]);
    }

    public function provider(): static
    {
        return $this->state(fn () => [
            'role' => UserRole::Provider,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'is_active' => false,
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn () => [
            'email_verified_at' => null,
        ]);
    }
}