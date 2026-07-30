<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $indexes = collect(Schema::getIndexes('reactions'))->pluck('name');

        if (! $indexes->contains('reactions_target_user_unique')) {
            $duplicates = \Illuminate\Support\Facades\DB::table('reactions')
                ->select('reactionable_type', 'reactionable_id', 'user_id')
                ->groupBy('reactionable_type', 'reactionable_id', 'user_id')
                ->havingRaw('COUNT(*) > 1')
                ->get()
                ->count();

            if ($duplicates > 0) {
                throw new \RuntimeException("Cannot add reactions_target_user_unique: duplicate user reactions exist in {$duplicates} target groups.");
            }

            Schema::table('reactions', function (Blueprint $table) {
                $table->unique(
                    ['reactionable_type', 'reactionable_id', 'user_id'],
                    'reactions_target_user_unique'
                );
            });
        }
    }

    public function down(): void
    {
        Schema::table('reactions', function (Blueprint $table) {
            $table->dropUnique('reactions_target_user_unique');
        });
    }
};

