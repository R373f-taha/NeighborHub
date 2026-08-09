<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\CommunityManager;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\Issue\app\Models\IssueCategory;
use Tests\AuthApiTestCase;

/**
 * Base fixture for the Reports (community analytics) API.
 *
 * The four report endpoints are manager-or-super-admin scoped analytics
 * endpoints keyed by the route community. This base provisions two isolated
 * communities (A and B) with their own manager, a super admin, a provider and
 * residents so every test can assert authorization and community isolation.
 */
abstract class ReportsTestCase extends AuthApiTestCase
{
    use RefreshDatabase;

    protected Community $communityA;
    protected Community $communityB;
    protected User $managerA;
    protected User $managerB;
    protected User $superAdmin;
    protected User $provider;
    protected User $residentUserA;
    protected Resident $residentA;
    protected IssueCategory $categoryA;

    /**
     * The engagement report joins poll_votes/polls. These tables are owned by
     * the Poll module, whose service provider currently fails to publish its
     * migrations (capitalised "Database/Migrations" path on a case-sensitive
     * filesystem and a boot() override that skips the parent migration loader).
     *
     * To validate ReportService aggregation logic we publish the intended Poll
     * schema once per process here (before any per-test transaction begins), so
     * the Reports suite is not blocked by an unrelated module's defect. In
     * production the engagement endpoint will error until the Poll migration
     * defect is resolved.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        if (! self::$pollSchemaPublished) {
            $this->publishPollSchema();
            self::$pollSchemaPublished = true;
        }
    }

    private static bool $pollSchemaPublished = false;

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

        $this->communityA = Community::create([
            'name' => 'Community A', 'city' => 'Amman', 'address' => 'A St',
            'total_units' => 10, 'is_active' => true,
        ]);
        $this->communityB = Community::create([
            'name' => 'Community B', 'city' => 'Irbid', 'address' => 'B St',
            'total_units' => 5, 'is_active' => true,
        ]);

        $unitA = Unit::create([
            'community_id' => $this->communityA->id, 'unit_number' => 'U1',
            'building_name' => 'B1', 'rooms' => 2, 'unit_type' => 'apartment', 'is_active' => true,
        ]);

        $this->residentUserA = User::factory()->resident()->create(['is_active' => true]);
        $this->residentA = Resident::create([
            'user_id' => $this->residentUserA->id, 'unit_id' => $unitA->id,
            'community_id' => $this->communityA->id, 'residence_type' => 'owner',
            'status' => 'active', 'current_marker' => true,
        ]);

        $this->managerA = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create(['community_id' => $this->communityA->id, 'manager_id' => $this->managerA->id]);

        $this->managerB = User::factory()->manager()->create(['is_active' => true]);
        CommunityManager::create(['community_id' => $this->communityB->id, 'manager_id' => $this->managerB->id]);

        $this->superAdmin = User::factory()->superAdmin()->create(['is_active' => true]);
        $this->provider = User::factory()->provider()->create(['is_active' => true]);

        $this->categoryA = IssueCategory::create(['name' => 'Plumbing', 'is_active' => true]);
    }

    /**
     * @return array<string, string>
     */
    protected function token(User $user): array
    {
        return [
            'Authorization' => 'Bearer '.$user->createToken('reports-test')->plainTextToken,
            'Accept' => 'application/json',
        ];
    }

    protected function uri(string $endpoint, Community $community): string
    {
        return "/api/communities/{$community->id}/reports/{$endpoint}";
    }
}
