<?php

declare(strict_types=1);

namespace Modules\Interaction\Database\Seeders;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Modules\Community\app\Models\Announcement;
use Modules\Community\app\Models\Community;
use Modules\Interaction\app\Models\Comment;
use Modules\Post\app\Models\Post;

class CommentSeeder extends Seeder
{
    private const COMMENTS_PER_COMMENTABLE = 5;
    private const REPLIES_PER_COMMENTABLE = 2;

    public function run(): void
    {
        if (app()->environment('production')) {
            return;
        }

        $residentsByCommunity = [];

        $residentsFor = function (Community $community) use (&$residentsByCommunity): Collection {
            return $residentsByCommunity[$community->getKey()] ??= $community->residents()
                ->where('status', 'active')
                ->with('user:id,name')
                ->get(['residents.id', 'residents.user_id']);
        };

        Post::query()
            ->with('community:id,name')
            ->chunkById(200, function (Collection $posts) use ($residentsFor): void {
                foreach ($posts as $post) {
                    $community = $post->community;

                    if ($community === null) {
                        continue;
                    }

                    $residents = $residentsFor($community);

                    if ($residents->isNotEmpty()) {
                        $this->seedCommentable($post, $residents);
                    }
                }
            });

        Announcement::query()
            ->with('community:id,name')
            ->chunkById(200, function (Collection $announcements) use ($residentsFor): void {
                foreach ($announcements as $announcement) {
                    $community = $announcement->community;

                    if ($community === null) {
                        continue;
                    }

                    $residents = $residentsFor($community);

                    if ($residents->isNotEmpty()) {
                        $this->seedCommentable($announcement, $residents);
                    }
                }
            });
    }

    /**
     * @param  Collection<int, \Modules\Community\app\Models\Resident>  $residents
     */
    private function seedCommentable(Model $commentable, Collection $residents): void
    {
        $existingTopLevel = Comment::query()
            ->where('commentable_type', $commentable->getMorphClass())
            ->where('commentable_id', $commentable->getKey())
            ->whereNull('parent_id')
            ->count();

        $missing = max(0, self::COMMENTS_PER_COMMENTABLE - $existingTopLevel);

        $parents = Collection::make();

        for ($i = 0; $i < $missing; $i++) {
            $author = $residents->random()->user;

            $comment = Comment::factory()
                ->forCommentable($commentable)
                ->create(['author_id' => $author->getKey()]);

            if ($i < self::REPLIES_PER_COMMENTABLE) {
                $parents->push($comment);
            }
        }

        foreach ($parents as $parent) {
            $replyAuthor = $residents->random()->user;

            Comment::factory()
                ->forCommentable($commentable)
                ->reply($parent)
                ->create(['author_id' => $replyAuthor->getKey()]);
        }
    }
}
