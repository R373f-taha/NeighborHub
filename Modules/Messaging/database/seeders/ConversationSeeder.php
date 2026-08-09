<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Community\app\Models\Community;
use Modules\Messaging\app\Models\Conversation;

class ConversationSeeder extends Seeder
{
    private const TARGET_PER_COMMUNITY = 6;

    public function run(): void
    {
        Community::query()
            ->each(function (Community $community): void {

                $existing = $community->conversations()->count();

                $missing = max(
                    0,
                    self::TARGET_PER_COMMUNITY - $existing
                );

                if ($missing === 0) {
                    return;
                }

                Conversation::factory()
                    ->count($missing)
                    ->forCommunity($community)
                    ->create();
            });
    }
}