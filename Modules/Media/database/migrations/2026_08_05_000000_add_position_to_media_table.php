<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->unsignedTinyInteger('position')->default(1)->after('disk');
        });

        // Deterministically backfill position per parent for any pre-existing
        // rows so the unique constraint below can be applied without conflicts.
        DB::statement(<<<'SQL'
            UPDATE media m
            JOIN (
                SELECT id,
                       ROW_NUMBER() OVER (
                           PARTITION BY mediable_type, mediable_id
                           ORDER BY id ASC
                       ) AS rn
                FROM media
            ) ranked ON ranked.id = m.id
            SET m.position = ranked.rn
        SQL);

        Schema::table('media', function (Blueprint $table): void {
            $table->unique(['mediable_type', 'mediable_id', 'position'], 'media_parent_position_unique');
        });
    }

    public function down(): void
    {
        Schema::table('media', function (Blueprint $table): void {
            $table->dropUnique('media_parent_position_unique');
        });

        Schema::table('media', function (Blueprint $table): void {
            $table->dropColumn('position');
        });
    }
};
