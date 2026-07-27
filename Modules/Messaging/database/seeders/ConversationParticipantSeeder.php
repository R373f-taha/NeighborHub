<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Models\ConversationParticipant;

class ConversationParticipantSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()
            ->get();


        Conversation::query()
            ->each(function (Conversation $conversation) use ($users) {


                /*
                 * كل Conversation يكون فيها
                 * 2 - 5 مشاركين
                 */

                $participants = $users
                    ->random(rand(2, 5));


                foreach ($participants as $user) {


                    ConversationParticipant::create([

                        'conversation_id' => $conversation->id,

                        'user_id' => $user->id,

                        'joined_at' => now(),

                        'left_at' => null,

                    ]);

                }

            });
    }
}