<?php

declare(strict_types=1);

namespace Modules\Issue\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Models\User;
use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Models\IssueStatusLog;

/**
 * @extends Factory<IssueStatusLog>
 */
class IssueStatusLogFactory extends Factory
{
    protected $model = IssueStatusLog::class;


    public function definition(): array
    {
        return [

            'issue_id' => Issue::factory(),

            'old_status' => null,

            'new_status' => fake()->randomElement([
                'open',
                'assigned',
                'in_progress',
                'resolved',
                'closed',
            ]),

            'changed_by' => User::factory()->manager(),

            'note' => fake()->optional()->sentence(),
        ];
    }
}