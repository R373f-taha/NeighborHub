<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Community\app\Models\Community;
use Modules\Messaging\app\Models\Conversation;

class ConversationSeeder extends Seeder
{
    public function run(): void
    {
        Community::query()
            ->each(function (Community $community) {

                Conversation::factory()
                    ->count(5)
                    ->create([
                        'community_id' => $community->id,
                    ]);

            });
    }
}