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

  public function configure(): static
{
    return $this->afterCreating(function (User $user) {
        if ($user->role instanceof UserRole) {
            $user->assignRole($user->role->value);
        }
    });
}
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),

            'password' => 'password',

            'role' => UserRole::Resident,

            'phone' => fake()->phoneNumber(),

            'avatar' => fake()->imageUrl(300, 300, 'people'),

            'is_active' => true,

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