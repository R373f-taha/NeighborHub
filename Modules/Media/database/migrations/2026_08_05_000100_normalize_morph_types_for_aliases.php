<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Modules\Post\app\Models\Post;
use Modules\ServiceListing\app\Models\ServiceListing;

/**
 * Normalizes existing polymorphic rows for the parents now using stable morph
 * aliases. Required because adopting the central morph map (Media module)
 * changes Post/ServiceListing morph types from full class names to aliases;
 * without this, pre-existing comment/reaction rows for those parents would be
 * orphaned from their relations. Idempotent: re-running matches nothing.
 *
 * Only the two aliased parents are converted. Announcement, Issue, Poll and
 * others are NOT aliased and keep their existing morph type untouched.
 */
return new class extends Migration
{
    // private const array MAP = [
    //     Post::class => 'post',
    //     ServiceListing::class => 'service_listing',
    // ];
 private const MAP = [
        Post::class => 'post',
        ServiceListing::class => 'service_listing',
    ];

    public function up(): void
    {
        $this->convert('comments', 'commentable_type');
        $this->convert('reactions', 'reactionable_type');
        $this->convert('media', 'mediable_type');
    }

    public function down(): void
    {
        foreach (['comments' => 'commentable_type', 'reactions' => 'reactionable_type', 'media' => 'mediable_type'] as $table => $column) {
            foreach (self::MAP as $class => $alias) {
                DB::table($table)->where($column, $alias)->update([$column => $class]);
            }
        }
    }

    private function convert(string $table, string $column): void
    {
        foreach (self::MAP as $class => $alias) {
            // Parameter binding avoids MySQL backslash-escape ambiguity when
            // comparing against the class name string.
            DB::table($table)->where($column, $class)->update([$column => $alias]);
        }
    }
};
