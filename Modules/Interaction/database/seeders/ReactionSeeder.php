<?php

declare(strict_types=1);

namespace Modules\Interaction\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Announcement;
use Modules\Interaction\app\Models\Reaction;
use Modules\Post\app\Models\Post;

class ReactionSeeder extends Seeder
{
    private const TARGET = 5000;

    public function run(): void
    {
        $missing = max(0, self::TARGET - Reaction::count());

        if ($missing === 0) {
            return;
        }

        $users = User::query()->get();

        if ($users->isEmpty()) {
            return;
        }

        $this->seedFor(Post::class, $users, $missing);

        $missing = max(0, self::TARGET - Reaction::count());

        if ($missing === 0) {
            return;
        }

        $this->seedFor(Announcement::class, $users, $missing);
    }

    private function seedFor(
        string $modelClass,
        $users,
        int $missing
    ): void {
        $modelClass::query()
            ->chunkById(200, function ($items) use ($users, &$missing): void {
                foreach ($items as $item) {
                    if ($missing <= 0) {
                        return;
                    }

                    $take = min(
                        random_int(1, 5),
                        $users->count(),
                        $missing
                    );

                    foreach ($users->random($take) as $user) {
                        if ($missing <= 0) {
                            break;
                        }

                        $exists = Reaction::query()
                            ->where('reactionable_type', $item->getMorphClass())
                            ->where('reactionable_id', $item->getKey())
                            ->where('user_id', $user->id)
                            ->exists();

                        if ($exists) {
                            continue;
                        }

                        Reaction::factory()
                            ->forReactionable($item)
                            ->create([
                                'user_id' => $user->id,
                            ]);

                        $missing--;
                    }
                }
            });
    }
}
