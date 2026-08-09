<?php

declare(strict_types=1);

namespace Modules\Issue\Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Issue\app\Models\IssueCategory;

class IssueCategorySeeder extends Seeder
{
    private const CATEGORIES = [
        'Plumbing',
        'Electricity',
        'Elevator',
        'Cleaning',
        'Security',
        'Other',
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $name) {
            IssueCategory::firstOrCreate(
                ['name' => $name],
                ['is_active' => true]
            );
        }
    }
}
