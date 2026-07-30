<?php

declare(strict_types=1);

namespace Modules\ServiceListing\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Community\app\Models\Community;
use Modules\ServiceListing\app\Models\ServiceListing;

class ServiceListingSeeder extends Seeder
{
    public function run(): void
    {
        Community::with('residents')
            ->get()
            ->each(function (Community $community) {

                if ($community->residents->isEmpty()) {
                    return;
                }

                ServiceListing::factory()
                    ->count(10)
                    ->create([
                        'community_id' => $community->id,
                        'resident_id'  => $community->residents->random()->id,
                    ]);
            });
    }
}