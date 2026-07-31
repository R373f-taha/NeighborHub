<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasColumn = Schema::hasColumn('residents', 'community_id');

        if (! $hasColumn) {
            Schema::table('residents', function (Blueprint $table) {
                $table->unsignedBigInteger('community_id')->nullable()->after('approved_by');
            });

            DB::statement('
                UPDATE residents
                INNER JOIN units ON residents.unit_id = units.id
                SET residents.community_id = units.community_id
            ');

            $orphaned = DB::table('residents')->whereNull('community_id')->count();
            if ($orphaned > 0) {
                throw new \RuntimeException(
                    "Cannot complete migration: {$orphaned} resident(s) have no resolvable community_id via their unit."
                );
            }

            Schema::table('residents', function (Blueprint $table) {
                $table->unsignedBigInteger('community_id')->nullable(false)->change();
            });
        } else {
            $col = DB::selectOne(
                "SELECT COLUMN_TYPE, IS_NULLABLE
                 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'residents'
                   AND COLUMN_NAME = 'community_id'"
            );

           if ($col) {
    $type = strtoupper($col->COLUMN_TYPE);

    if ($type !== 'BIGINT(20) UNSIGNED') {
        throw new \RuntimeException(
            "residents.community_id has unexpected type: {$col->COLUMN_TYPE}"
        );
    }
}

            if ($col && strtoupper($col->IS_NULLABLE) === 'YES') {
                $nullCount = DB::table('residents')->whereNull('community_id')->count();
                if ($nullCount > 0) {
                    throw new \RuntimeException(
                        "Cannot enforce NOT NULL: {$nullCount} resident(s) have null community_id."
                    );
                }
                Schema::table('residents', function (Blueprint $table) {
                    $table->unsignedBigInteger('community_id')->nullable(false)->change();
                });
            }

            $mismatched = DB::table('residents')
                ->join('units', 'residents.unit_id', '=', 'units.id')
                ->whereColumn('residents.community_id', '!=', 'units.community_id')
                ->count();

            if ($mismatched > 0) {
                throw new \RuntimeException(
                    "Data inconsistency: {$mismatched} resident(s) have community_id mismatching their unit's community."
                );
            }
        }

        $fk = DB::selectOne(
            "SELECT CONSTRAINT_NAME, DELETE_RULE
             FROM information_schema.REFERENTIAL_CONSTRAINTS
             WHERE CONSTRAINT_SCHEMA = DATABASE()
               AND TABLE_NAME = 'residents'
               AND REFERENCED_TABLE_NAME = 'communities'
               AND CONSTRAINT_NAME IN (
                   SELECT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE
                   WHERE TABLE_SCHEMA = DATABASE()
                     AND TABLE_NAME = 'residents'
                     AND COLUMN_NAME = 'community_id'
                     AND REFERENCED_TABLE_NAME = 'communities'
               )"
        );

        if (! $fk) {
            Schema::table('residents', function (Blueprint $table) {
                $table->foreign('community_id')->references('id')->on('communities')->cascadeOnDelete();
            });
        }

        $hasIndex = collect(
            DB::select("SHOW INDEX FROM residents WHERE Column_name = 'community_id'")
        )->isNotEmpty();

        if (! $hasIndex) {
            Schema::table('residents', function (Blueprint $table) {
                $table->index('community_id');
            });
        }
    }

    // community_id is part of the canonical schema defined in the historical
    // create_residents_table migration; dropping it here would break fresh installs.
    public function down(): void {}
};
