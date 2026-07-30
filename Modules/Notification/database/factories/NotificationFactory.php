<?php

declare(strict_types=1);

namespace Modules\Notification\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Models\User;
use Modules\Notification\app\Models\Notification;

/**
 * @extends Factory<Notification>
 */
class NotificationFactory extends Factory
{
    protected $model = Notification::class;


    public function definition(): array
    {
        return [

            'user_id' => User::factory(),

            'title' => fake()->sentence(4),

            'body' => fake()->paragraph(),

            'type' => fake()->randomElement([
                'announcement',
                'issue',
                'poll',
                'message',
                'system',
            ]),

            'data' => [
                'action' => fake()->word(),
            ],

            'read_at' => fake()->boolean(40)
                ? now()
                : null,


            /*
             * يتم تحديدها في Seeder
             */
            'notifiable_type' => null,

            'notifiable_id' => null,
        ];
    }


    public function unread(): static
    {
        return $this->state(fn () => [
            'read_at' => null,
        ]);
    }


    public function read(): static
    {
        return $this->state(fn () => [
            'read_at' => now(),
        ]);
    }
}