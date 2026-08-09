<?php

declare(strict_types=1);

namespace Modules\Media\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Announcement;
use Modules\Issue\app\Models\Issue;
use Modules\Media\app\Models\Media;

class MediaSeeder extends Seeder
{
    private const MEDIA_PER_PARENT = 2;

    public function run(): void
    {
        $users = User::query()->get();

        if ($users->isEmpty()) {
            return;
        }

        Announcement::query()
            ->each(function (Announcement $announcement) use ($users): void {
                Media::factory()
                    ->count(self::MEDIA_PER_PARENT)
                    ->sequence(
                        ['position' => 1],
                        ['position' => 2],
                    )
                    ->create([
                        'mediable_type' => Announcement::class,
                        'mediable_id' => $announcement->id,
                        'uploaded_by' => $users->random()->id,
                    ]);
            });

        Issue::query()
            ->each(function (Issue $issue) use ($users): void {
                Media::factory()
                    ->count(self::MEDIA_PER_PARENT)
                    ->sequence(
                        ['position' => 1],
                        ['position' => 2],
                    )
                    ->create([
                        'mediable_type' => Issue::class,
                        'mediable_id' => $issue->id,
                        'uploaded_by' => $users->random()->id,
                    ]);
            });
    }
}