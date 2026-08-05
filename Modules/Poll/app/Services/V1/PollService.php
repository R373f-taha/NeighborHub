<?php

declare(strict_types=1);

namespace Modules\Poll\app\Services\V1;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Models\PollOption;
use Modules\Poll\app\Models\PollVote;
use Modules\Poll\app\Enums\PollStatus;
use Modules\Community\app\Models\Community;
use Modules\Auth\app\Models\User;
use Modules\Poll\app\Events\PollCreated;
use Modules\Poll\app\Traits\CacheableTrait;


class PollService
{
    use CacheableTrait;

    private const LIST_TTL = 600; // 10 minutes
  public function getCommunityPolls(int $communityId, array $filters = [])
    {
        $cacheKey = "poll_list_community_{$communityId}";

        return $this->rememberWithLock($cacheKey, self::LIST_TTL, function () use ($communityId, $filters) {
            $query = Poll::where('community_id', $communityId)
                ->with(['options', 'creator'])
                ->orderBy('created_at', 'desc');

            if (isset($filters['status'])) {
                $query->where('status', $filters['status']);
            }

            if (isset($filters['type'])) {
                $query->where('type', $filters['type']);
            }

            return $query->paginate(20);
        });
    }

    // ========== Create Poll ==========

    public function createPoll(Community $community, User $creator, array $data): Poll
    {
        return DB::transaction(function () use ($community, $creator, $data) {

            $poll = Poll::create([
                'community_id' => $community->id,
                'created_by' => $creator->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' =>"single_choice",
                'status' => PollStatus::Draft,
                'ends_at' => $data['ends_at'],
            ]);

            foreach ($data['options'] as $index => $optionText) {
                PollOption::create([
                    'poll_id' => $poll->id,
                    'text' => $optionText,
                    'position' => $index,
                ]);
            }

            // 3. Clear community cache
            $this->clearCommunityCache($community->id);

            Log::info('📊 Poll created', [
                'poll_id' => $poll->id,
                'community_id' => $community->id,
                'created_by' => $creator->id,
                'title' => $poll->title,
            ]);

           PollCreated::dispatch($poll);

            return $poll;
        });
    }






}
