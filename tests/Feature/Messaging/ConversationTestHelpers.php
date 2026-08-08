<?php

declare(strict_types=1);

namespace Tests\Feature\Messaging;

use Illuminate\Support\Carbon;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Models\ConversationParticipant;
use Modules\Messaging\app\Models\Message;

/**
 * Low-level helpers for Messaging API tests. No assertions here.
 */
trait ConversationTestHelpers
{
    public function call($method, $uri, $parameters = [], $cookies = [], $files = [], $server = [], $content = null)
    {
        // Sanctum's request guard caches the resolved user for the in-memory
        // app lifetime; drop cached guards before each request so identity is
        // re-resolved (mirrors production, one process per request).
        if ($this->app && $this->app->bound('auth')) {
            $this->app['auth']->forgetGuards();
        }

        return parent::call($method, $uri, $parameters, $cookies, $files, $server, $content);
    }

    /** @return array<string, string> */
    private function token(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$user->createToken('msg-test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    private function makeCommunity(string $name = 'Comm'): Community
    {
        return Community::create([
            'name' => $name,
            'city' => 'Beirut',
            'address' => 'Ring',
        ]);
    }

    private function makeConversation(Community $community, ?Carbon $createdAt = null): Conversation
    {
        $conversation = new Conversation();
        $conversation->community_id = $community->id;
        $conversation->type = 'direct';
        $conversation->status = 'active';
        $conversation->timestamps = false;
        $conversation->created_at = $createdAt ?? now();
        $conversation->updated_at = $createdAt ?? now();
        $conversation->save();

        return $conversation;
    }

    /**
     * Create a participant row with full timestamp control. Defaults to an
     * ACTIVE member (left_at = null).
     */
    private function makeParticipant(
        Conversation $conversation,
        User $user,
        ?Carbon $joinedAt = null,
        ?Carbon $leftAt = null,
    ): ConversationParticipant {
        $joinedAt ??= now();

        $participant = new ConversationParticipant();
        $participant->conversation_id = $conversation->id;
        $participant->user_id = $user->id;
        $participant->timestamps = false;
        $participant->joined_at = $joinedAt;
        $participant->left_at = $leftAt;
        $participant->created_at = $joinedAt;
        $participant->updated_at = $leftAt ?? $joinedAt;
        $participant->save();

        return $participant;
    }

    private function makeMessage(
        Conversation $conversation,
        Community $community,
        User $sender,
        ?Carbon $createdAt = null,
        string $content = 'hello',
    ): Message {
        $createdAt ??= now();
        $message = new Message();
        $message->conversation_id = $conversation->id;
        $message->community_id = $community->id;
        $message->sender_id = $sender->id;
        $message->content = $content;
        $message->timestamps = false;
        $message->created_at = $createdAt;
        $message->updated_at = $createdAt;
        $message->save();

        return $message;
    }

    private function indexUri(Community $community): string
    {
        return "/api/v1/communities/{$community->id}/conversations";
    }

    private function messagesUri(Community $community, Conversation|int $conversation): string
    {
        $id = $conversation instanceof Conversation ? $conversation->id : $conversation;

        return "/api/v1/communities/{$community->id}/conversations/{$id}/messages";
    }

    private function readUri(Community $community, Conversation|int $conversation): string
    {
        $id = $conversation instanceof Conversation ? $conversation->id : $conversation;

        return "/api/v1/communities/{$community->id}/conversations/{$id}/read";
    }

    private function makeUnit(Community $community, string $number = 'U1'): \Modules\Community\app\Models\Unit
    {
        return \Modules\Community\app\Models\Unit::create([
            'community_id' => $community->id,
            'unit_number' => $number,
            'building_name' => 'B',
            'rooms' => 2,
            'unit_type' => 'apartment',
            'is_active' => true,
        ]);
    }

    private function makeResident(
        Community $community,
        \Modules\Community\app\Models\Unit $unit,
        User $user,
        string $status = 'active',
        string $residenceType = 'owner',
    ): \Modules\Community\app\Models\Resident {
        return \Modules\Community\app\Models\Resident::create([
            'user_id' => $user->id,
            'unit_id' => $unit->id,
            'community_id' => $community->id,
            'residence_type' => $residenceType,
            'status' => $status,
            'current_marker' => true,
        ]);
    }
}
