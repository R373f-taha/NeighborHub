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
            ->each(function (Notification $notification) {


                NotificationLog::factory()
                    ->create([

                        'notification_id' => $notification->id,

                    ]);

            });
    }
}