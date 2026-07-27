<?php

declare(strict_types=1);

namespace Modules\Notification\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Notification\app\Models\Notification;
use Modules\Notification\app\Models\NotificationLog;

/**
 * @extends Factory<NotificationLog>
 */
class NotificationLogFactory extends Factory
{
    protected $model = NotificationLog::class;


    public function definition(): array
    {
        return [

            'notification_id' => Notification::factory(),

            'channel' => fake()->randomElement([
                'email',
                'push',
                'sms',
                'database',
            ]),


            'status' => fake()->randomElement([
                'pending',
                'sent',
                'failed',
                'delivered',
            ]),


            'sent_at' => fake()->boolean(70)
                ? now()
                : null,
        ];
    }
}