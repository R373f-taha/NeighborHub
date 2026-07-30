<?php

declare(strict_types=1);

namespace Modules\Interaction\app\Services;

use Illuminate\Support\Facades\DB;
use Modules\Auth\app\Models\User;
use Modules\Interaction\app\Enums\ReactionType;
use Modules\Interaction\app\Models\Reaction;
use Modules\Post\app\Models\Post;

class ReactionService
{
    public function toggle(Post $post, User $user, ReactionType $type, int $attempts = 0): array
    {
        try {
            return DB::transaction(function () use ($post, $user, $type) {
                $existing = $post->reactions()
                    ->where('user_id', $user->id)
                    ->lockForUpdate()
                    ->first();

                if ($existing === null) {
                    $reaction = new Reaction(['type' => $type]);
                    $reaction->user()->associate($user);
                    $post->reactions()->save($reaction);

                    return [
                        'action' => 'created',
                        'reaction' => $reaction,
                        'reactions_count' => $post->reactions()->count(),
                    ];
                }

                if ($existing->type !== $type) {
                    $existing->update(['type' => $type]);

                    return [
                        'action' => 'updated',
                        'reaction' => $existing,
                        'reactions_count' => $post->reactions()->count(),
                    ];
                }

                $existing->delete();

                return [
                    'action' => 'removed',
                    'reaction' => null,
                    'reactions_count' => $post->reactions()->count(),
                ];
            });
        } catch (\Illuminate\Database\QueryException $e) {
            if ($attempts === 0 && $this->isUniqueConstraintViolation($e)) {
                return $this->toggle($post, $user, $type, 1);
            }
            throw $e;
        }
    }

    private function isUniqueConstraintViolation(\Illuminate\Database\QueryException $exception): bool
    {
        $sqlState = (string) $exception->getCode();
        $errorInfoState = (string) ($exception->errorInfo[0] ?? '');
        $message = $exception->getMessage();
        
        return ($sqlState === '23000' || $errorInfoState === '23000') && (
            str_contains($message, 'reactions_target_user_unique') || 
            str_contains($message, 'Duplicate entry')
        );
    }
}
