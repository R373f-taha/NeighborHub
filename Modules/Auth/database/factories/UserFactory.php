<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Modules\Auth\app\Enums\UserRole;
use Modules\Auth\app\Models\User;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /**
     * Mirror the users.role enum onto the Spatie role when one is set. The role
     * is only assigned; it is not created here, so RBAC definitions stay owned
     * by the seeder/test bootstrap.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ($user->role instanceof UserRole && ! $user->hasRole($user->role->value)) {
                $user->assignRole($user->role->value);
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

            'password' => Hash::make('password'),

            'role' => UserRole::Resident,

            'phone' => fake()->phoneNumber(),

            'avatar' => null,

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
