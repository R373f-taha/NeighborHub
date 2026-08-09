<?php

declare(strict_types=1);

namespace Modules\Interaction\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Modules\Community\app\Models\Announcement;
use Modules\Interaction\app\Models\Comment;
use Modules\Post\app\Models\Post;

class CommentSeeder extends Seeder
{
    private const TARGET = 3000;

    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $missing = max(0, self::TARGET - Comment::count());

        if ($missing === 0) {
            return;
        }

        $this->seedFor(Post::class, $missing);

        $missing = max(0, self::TARGET - Comment::count());

        if ($missing === 0) {
            return;
        }

        $this->seedFor(Announcement::class, $missing);
    }

    private function seedFor(string $modelClass, int $missing): void
    {
        $modelClass::query()
            ->with('community')
            ->chunkById(200, function (Collection $items) use (&$missing): void {
                foreach ($items as $item) {
                    if ($missing <= 0) {
                        return;
                    }

                    $community = $item->community;

                    if ($community === null) {
                        continue;
                    }

                    $residents = $community->residents()
                        ->where('status', 'active')
                        ->with('user')
                        ->get();

                    if ($residents->isEmpty()) {
                        continue;
                    }

                    $this->createComments($item, $residents, $missing);
                }
            });
    }

    private function createComments(
        Model $commentable,
        Collection $residents,
        int &$missing
    ): void {
        $existing = Comment::query()
            ->where('commentable_type', $commentable->getMorphClass())
            ->where('commentable_id', $commentable->getKey())
            ->count();

        $count = min(
            random_int(1, 4),
            $missing
        );

        if ($existing > 0) {
            return;
        }

        $parents = [];

        for ($i = 0; $i < $count; $i++) {
            $resident = $residents->random();

            $comment = Comment::factory()
                ->forCommentable($commentable)
                ->create([
                    'author_id' => $resident->user_id,
                ]);

            $parents[] = $comment;
            $missing--;
        }

        if ($missing > 0 && count($parents) > 0 && random_int(1, 100) <= 30) {
            $parent = $parents[array_rand($parents)];
            $resident = $residents->random();

            Comment::factory()
                ->forCommentable($commentable)
                ->reply($parent)
                ->create([
                    'author_id' => $resident->user_id,
                ]);

            $missing--;
        }
    }
}
