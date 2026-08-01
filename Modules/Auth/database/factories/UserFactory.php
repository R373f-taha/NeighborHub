<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Models\User;
use Spatie\Permission\Models\Role;

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
     * Keep Spatie roles in sync with the legacy users.role enum for any user
     * created through the factory. The role is created on demand (idempotent)
     * so tests that have not seeded roles still resolve, while tests that have
     * seeded roles reuse the existing ones.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            $roleName = $user->role?->value;

            if ($roleName === null) {
                return;
            }

            Role::firstOrCreate(['name' => $roleName, 'guard_name' => 'web']);

            if (! $user->hasRole($roleName)) {
                $user->assignRole($roleName);
            }
        });
    }

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