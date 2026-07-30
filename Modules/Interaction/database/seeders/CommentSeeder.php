<?php

declare(strict_types=1);

namespace Modules\Interaction\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Announcement;
use Modules\Interaction\app\Models\Comment;

class CommentSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::query()->get();


        Announcement::query()
            ->each(function ($announcement) use ($users) {


                $comments = Comment::factory()
                    ->count(5)
                    ->create([
                        'commentable_type' => Announcement::class,

                        'commentable_id' => $announcement->id,

                        'author_id' => $users->random()->id,
                    ]);


                // Replies
                foreach ($comments->take(2) as $comment) {

                    Comment::factory()
                        ->reply()
                        ->create([
                            'commentable_type' => Announcement::class,

                            'commentable_id' => $announcement->id,

                            'author_id' => $users->random()->id,

                            'parent_id' => $comment->id,
                        ]);
                }

            });
    }
}