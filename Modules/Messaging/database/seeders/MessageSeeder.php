<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Models\Message;

class MessageSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->get();


        Conversation::query()
            ->each(function (Conversation $conversation) use ($users) {


                Message::factory() ->count(rand(10, 20))->create([

                        'conversation_id' => $conversation->id,

                        'sender_id' => $users->random()->id,

                        'community_id' => $conversation->community_id,

                    ]);

            });
    }
}