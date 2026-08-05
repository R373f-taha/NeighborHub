<?php

namespace Modules\Issue\Database\Factories;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Issue\app\Models\IssueStatusLog;
use Modules\Issue\app\Enums\IssueStatus;
use Modules\Issue\app\Models\Issue;
use Modules\Auth\app\Models\User;

class IssueStatusLogFactory extends Factory
{
    protected $model = IssueStatusLog::class;


    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'old_status' => null,
            'new_status' => IssueStatus::OPEN,
            'changed_by' => User::factory(),
            'note' => fake()->optional()->sentence(),
        ];
    }
}