<?php

declare(strict_types=1);

namespace Tests\Feature\Poll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Models\PollOption;
use Tests\AuthApiTestCase;

/**
 * Base fixture for Poll service-level tests.
 *
 * The Poll module cannot boot in production (PollServiceProvider uses a
 * capitalised "Routes/api.php" / "Database/Migrations" path on a case-sensitive
 * filesystem and overrides boot() without the parent migration loader), so its
 * routes and tables never exist. To validate the poll voting / status logic
 * directly we provision the intended Poll schema once per process here.
 */
abstract class PollServiceTestCase extends AuthApiTestCase
{
    use RefreshDatabase;

    private static bool $pollSchemaPublished = false;

    protected Community $community;
    protected User $creator;
    protected Resident $resident;

    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        if (! self::$pollSchemaPublished) {
            $this->publishPollSchema();
            self::$pollSchemaPublished = true;
        }
    }

    private function publishPollSchema(): void
    {
        if (! Schema::hasTable('polls')) {
            Schema::create('polls', function ($table) {
                $table->id();
                $table->foreignId('community_id')->constrained('communities')->cascadeOnDelete();
                $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
                $table->string('title');
                $table->text('description')->nullable();
                $table->enum('type', ['single_choice'])->default('single_choice');
                $table->enum('status', ['draft', 'active', 'closed'])->default('draft');
                $table->timestamp('ends_at');
                $table->timestamp('activated_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('poll_options')) {
            Schema::create('poll_options', function ($table) {
                $table->id();
                $table->foreignId('poll_id')->constrained('polls')->cascadeOnDelete();
                $table->string('text');
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('poll_votes')) {
            Schema::create('poll_votes', function ($table) {
                $table->id();
                $table->foreignId('poll_id')->constrained('polls')->cascadeOnDelete();
                $table->foreignId('option_id')->constrained('poll_options')->cascadeOnDelete();
                $table->timestamp('submitted_at')->useCurrent();
                $table->foreignId('voter_id')->constrained('residents')->cascadeOnDelete();
                $table->date('voted_at');
                $table->unique(['poll_id', 'voter_id']);
            });
        }
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->community = Community::create([
            'name' => 'Poll Community', 'city' => 'C', 'address' => 'A',
            'total_units' => 5, 'is_active' => true,
        ]);
        $unit = Unit::create([
            'community_id' => $this->community->id, 'unit_number' => 'U1',
            'building_name' => 'B', 'rooms' => 2, 'unit_type' => 'apartment', 'is_active' => true,
        ]);

        $this->creator = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create(['community_id' => $this->community->id, 'manager_id' => $this->creator->id]);

        $voterUser = User::factory()->resident()->create(['is_active' => true]);
        $this->resident = Resident::create([
            'user_id' => $voterUser->id, 'unit_id' => $unit->id,
            'community_id' => $this->community->id, 'residence_type' => 'owner',
            'status' => 'active', 'current_marker' => true,
        ]);
    }

    /**
     * @return array{0: Poll, 1: PollOption, 2: PollOption}
     */
    protected function makePoll(string $status = 'active', mixed $endsAt = null): array
    {
        $poll = Poll::create([
            'community_id' => $this->community->id,
            'created_by' => $this->creator->id,
            'title' => 'Poll title',
            'description' => 'desc',
            'type' => 'single_choice',
            'status' => $status,
            'ends_at' => $endsAt ?? now()->addDays(3),
        ]);

        $optA = PollOption::create(['poll_id' => $poll->id, 'text' => 'Yes']);
        $optB = PollOption::create(['poll_id' => $poll->id, 'text' => 'No']);

        return [$poll, $optA, $optB];
    }
}
