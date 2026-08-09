<?php

declare(strict_types=1);

namespace Modules\Issue\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Auth\app\Models\User;
use Modules\Issue\app\Enums\IssueStatus;
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
            'issue_id' => null,
            'old_status' => null,
            'new_status' => IssueStatus::OPEN,
            'changed_by' => User::where('role', 'manager')->inRandomOrder()->value('id'),
            'note' => fake()->optional()->sentence(),
        ];
    }
}