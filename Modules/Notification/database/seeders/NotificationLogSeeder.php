<?php

declare(strict_types=1);

namespace Modules\Notification\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Notification\app\Models\Notification;
use Modules\Notification\app\Models\NotificationLog;

class NotificationLogSeeder extends Seeder
{
    public function run(): void
    {
        Notification::query()
            ->each(function (Notification $notification): void {
                NotificationLog::updateOrCreate(
                    [
                        'notification_id' => $notification->id,
                    ],
                    [
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
                    ]
                );
            });
    }
}
