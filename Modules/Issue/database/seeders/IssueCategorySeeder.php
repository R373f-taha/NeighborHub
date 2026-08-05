<?php

namespace Modules\Issue\Database\Seeders;
use Illuminate\Database\Seeder;
use Modules\Issue\app\Models\IssueCategory;


class IssueCategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Plumbing',
            'Electricity',
            'Elevator',
            'Cleaning',
            'Security',
            'Other',
        ];


        foreach ($categories as $category) {

            IssueCategory::firstOrCreate([
                'name' => $category,
            ]);

        }
    }
}