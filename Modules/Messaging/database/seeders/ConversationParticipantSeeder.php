<?php

declare(strict_types=1);

namespace Modules\Messaging\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Models\ConversationParticipant;

class ConversationParticipantSeeder extends Seeder
{
    private const MIN_PARTICIPANTS = 2;
    private const MAX_PARTICIPANTS = 5;

    public function run(): void
    {
        Conversation::query()
            ->with('community')
            ->each(function (Conversation $conversation): void {

                $community = $conversation->community;

                if (!$community) {
                    return;
                }


                $residentUserIds = Resident::query()
                    ->where('community_id', $community->id)
                    ->where('status', 'active')
                    ->pluck('user_id');


                $managerUserIds = $community
                    ->managers()
                    ->pluck('users.id');

                $availableUserIds = $residentUserIds
                    ->merge($managerUserIds)
                    ->unique()
                    ->values();

                if ($availableUserIds->count() < self::MIN_PARTICIPANTS) {
                    return;
                }


                $maxParticipants = min(
                    self::MAX_PARTICIPANTS,
                    $availableUserIds->count()
                );

                $numberOfParticipants = fake()->numberBetween(
                    self::MIN_PARTICIPANTS,
                    $maxParticipants
                );

                $participants = $availableUserIds
                    ->random($numberOfParticipants);

                foreach ($participants as $userId) {

                    ConversationParticipant::updateOrCreate(
                        [
                            'conversation_id' => $conversation->id,
                            'user_id' => $userId,
                        ],
                        [
                            'joined_at' => now(),
                            'left_at' => null,
                        ]
                    );
                }
            });
    }
}