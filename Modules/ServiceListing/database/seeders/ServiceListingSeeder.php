<?php

declare(strict_types=1);

namespace Modules\ServiceListing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Community\app\Models\Community;
use Modules\ServiceListing\app\Models\ServiceListing;

class ServiceListingSeeder extends Seeder
{
    private const PER_COMMUNITY = 10;

    public function run(): void
    {
        Community::query()->each(function (Community $community): void {
            $residents = $community->residents()
                ->where('status', 'active')
                ->get();

            if ($residents->isEmpty()) {
                return;
            }

            ServiceListing::factory()
                ->count(self::PER_COMMUNITY)
                ->create([
                    'community_id' => $community->id,
                    'resident_id' => $residents->random()->id,
                ]);
        });
    }
}