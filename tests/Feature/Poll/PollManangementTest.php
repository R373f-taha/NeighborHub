<?php

declare(strict_types=1);

namespace Modules\Poll\Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Modules\Auth\app\Models\User;
use Modules\Community\app\Models\Community;
use Modules\Community\app\Models\Resident;
use Modules\Community\app\Models\Unit;
use Modules\Poll\app\Models\Poll;
use Modules\Poll\app\Models\PollOption;
use Carbon\Carbon;

/**
 * Poll Management Feature Test Suite
 *
 * Tests the complete poll lifecycle matching the actual controllers:
 * - PollController (index, store, show)
 * - VotesManagementController (vote, results)
 * - ChangePollStatusController (activate, close)
 */
class PollManagementTest extends TestCase
{
    use RefreshDatabase;

    private Community $community;
    private User $manager;
    private User $resident;
    private Resident $residentRecord;

    protected function setUp(): void
    {
        parent::setUp();

        Log::info('🏗️ Setting up PollManagementTest');

        // Create community
        $this->community = Community::factory()->create([
            'name' => 'Test Community',
            'city' => 'Amman',
            'is_active' => true,
        ]);

        // Create manager
        $this->manager = User::factory()->create(['role' => 'manager']);
        $this->community->managers()->attach($this->manager->id);

        // Create resident
        $this->resident = User::factory()->create(['role' => 'resident']);

        // Create unit
        $unit = Unit::factory()->create([
            'community_id' => $this->community->id,
            'unit_number' => 'A101',
            'is_active' => true,
        ]);

        // Create resident record
        $this->residentRecord = Resident::factory()->create([
            'user_id' => $this->resident->id,
            'community_id' => $this->community->id,
            'unit_id' => $unit->id,
            'status' => 'active',
            'current_marker' => true,
        ]);

        Log::info('✅ Test setup completed');
    }

    // ============================================
    // POLL CONTROLLER TESTS (index, store, show)
    // ============================================

    /**
     * Test that a manager can create a poll via PollController::store().
     */
    public function test_manager_can_create_poll(): void
    {
        Log::info('🔐 Testing: Manager creates a poll via PollController::store()');

        $this->actingAs($this->manager, 'sanctum');

        $response = $this->postJson("/api/v1/communities/{$this->community->id}/polls", [
            'title' => 'What is your favorite color?',
            'description' => 'Choose your favorite color',
            'type' => 'single_choice',
            'ends_at' => Carbon::now()->addDays(7)->toISOString(),
            'options' => ['Red', 'Blue', 'Green', 'Yellow'],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('polls', [
            'community_id' => $this->community->id,
            'title' => 'What is your favorite color?',
            'status' => 'draft',
        ]);

        Log::info('✅ Manager created poll successfully');
    }

    /**
     * Test that a resident can view polls via PollController::index().
     */
    public function test_resident_can_view_polls(): void
    {
        Log::info('🔐 Testing: Resident views polls via PollController::index()');

        // Create a poll
        $poll = $this->createDraftPoll();

        $this->actingAs($this->resident, 'sanctum');

        $response = $this->getJson("/api/v1/communities/{$this->community->id}/polls");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'title',
                    'status',
                ],
            ],
        ]);

        Log::info('✅ Resident viewed polls successfully');
    }

    /**
     * Test that a resident can view a specific poll via PollController::show().
     */
    public function test_resident_can_view_specific_poll(): void
    {
        Log::info('🔐 Testing: Resident views specific poll via PollController::show()');

        $poll = $this->createDraftPoll();

        $this->actingAs($this->resident, 'sanctum');

        $response = $this->getJson("/api/v1/communities/{$this->community->id}/polls/{$poll->id}");

        $response->assertStatus(200);
        $response->assertJson([
            'data' => [
                'id' => $poll->id,
                'title' => $poll->title,
            ],
        ]);

        Log::info('✅ Resident viewed specific poll successfully');
    }

    // ============================================
    // CHANGE POLL STATUS CONTROLLER TESTS (activate, close)
    // ============================================

    /**
     * Test that a manager can activate a poll via ChangePollStatusController::activate().
     */
    public function test_manager_can_activate_poll(): void
    {
        Log::info('🔐 Testing: Manager activates poll via ChangePollStatusController::activate()');

        $poll = $this->createDraftPoll();

        $this->actingAs($this->manager, 'sanctum');

        $response = $this->postJson("/api/v1/communities/{$this->community->id}/polls/{$poll->id}/activate");

        $response->assertStatus(200);
        $this->assertDatabaseHas('polls', [
            'id' => $poll->id,
            'status' => 'active',
        ]);

        Log::info('✅ Poll activated successfully');
    }

    /**
     * Test that a manager can close a poll via ChangePollStatusController::close().
     */
    public function test_manager_can_close_poll(): void
    {
        Log::info('🔐 Testing: Manager closes poll via ChangePollStatusController::close()');

        $poll = $this->createActivePoll();

        $this->actingAs($this->manager, 'sanctum');

        $response = $this->postJson("/api/v1/communities/{$this->community->id}/polls/{$poll->id}/close");

        $response->assertStatus(200);
        $this->assertDatabaseHas('polls', [
            'id' => $poll->id,
            'status' => 'closed',
        ]);

        Log::info('✅ Poll closed successfully');
    }

    // ============================================
    // VOTES MANAGEMENT CONTROLLER TESTS (vote, results)
    // ============================================

    /**
     * Test that a resident can vote via VotesManagementController::vote().
     */
    public function test_resident_can_vote(): void
    {
        Log::info('🔐 Testing: Resident votes via VotesManagementController::vote()');

        $poll = $this->createActivePoll();
        $option = $poll->options->first();

        $this->actingAs($this->resident, 'sanctum');

        $response = $this->postJson("/api/v1/communities/{$this->community->id}/polls/{$poll->id}/vote", [
            'option_id' => $option->id,
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
            'message' => 'Vote recorded successfully.',
        ]);

        $this->assertDatabaseHas('poll_votes', [
            'poll_id' => $poll->id,
            'poll_option_id' => $option->id,
            'resident_membership_id' => $this->residentRecord->id,
        ]);

        Log::info('✅ Resident voted successfully');
    }

    /**
     * Test that a resident cannot vote twice.
     */
    public function test_resident_cannot_vote_twice(): void
    {
        Log::info('🔐 Testing: Resident cannot vote twice');

        $poll = $this->createActivePoll();
        $option = $poll->options->first();

        $this->actingAs($this->resident, 'sanctum');

        // First vote
        $this->postJson("/api/v1/communities/{$this->community->id}/polls/{$poll->id}/vote", [
            'option_id' => $option->id,
        ]);

        // Second vote (should fail)
        $response = $this->postJson("/api/v1/communities/{$this->community->id}/polls/{$poll->id}/vote", [
            'option_id' => $option->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'You have already voted on this poll.',
        ]);

        Log::info('✅ Double voting correctly prevented');
    }

    /**
     * Test that a resident cannot vote on a closed poll.
     */
    public function test_resident_cannot_vote_on_closed_poll(): void
    {
        Log::info('🔐 Testing: Resident cannot vote on closed poll');

        $poll = $this->createClosedPoll();
        $option = $poll->options->first();

        $this->actingAs($this->resident, 'sanctum');

        $response = $this->postJson("/api/v1/communities/{$this->community->id}/polls/{$poll->id}/vote", [
            'option_id' => $option->id,
        ]);

        $response->assertStatus(422);
        $response->assertJson([
            'success' => false,
            'message' => 'This poll is not active or has ended.',
        ]);

        Log::info('✅ Voting on closed poll correctly prevented');
    }

    /**
     * Test that a manager can view results via VotesManagementController::results().
     */
    public function test_manager_can_view_results(): void
    {
        Log::info('🔐 Testing: Manager views results via VotesManagementController::results()');

        $poll = $this->createClosedPoll();

        $this->actingAs($this->manager, 'sanctum');

        $response = $this->getJson("/api/v1/communities/{$this->community->id}/polls/{$poll->id}/results");

        $response->assertStatus(200);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'poll_id',
                'title',
                'total_votes',
                'turnout',
                'results',
            ],
        ]);

        Log::info('✅ Manager viewed results successfully');
    }

    // ============================================
    // HELPER METHODS
    // ============================================

    private function createDraftPoll(): Poll
    {
        $poll = Poll::factory()->create([
            'community_id' => $this->community->id,
            'created_by' => $this->manager->id,
            'title' => 'Test Poll ' . uniqid(),
            'description' => 'Test description',
            'type' => 'single_choice',
            'status' => 'draft',
            'ends_at' => Carbon::now()->addDays(7),
        ]);

        PollOption::factory()->create(['poll_id' => $poll->id, 'label' => 'Option A', 'position' => 1]);
        PollOption::factory()->create(['poll_id' => $poll->id, 'label' => 'Option B', 'position' => 2]);

        return $poll;
    }

    private function createActivePoll(): Poll
    {
        $poll = $this->createDraftPoll();
        $poll->update(['status' => 'active', 'activated_at' => now()]);
        return $poll;
    }

    private function createClosedPoll(): Poll
    {
        $poll = $this->createActivePoll();

        // Add a vote
        $resident2 = User::factory()->create(['role' => 'resident']);
        $unit2 = Unit::factory()->create(['community_id' => $this->community->id]);
        Resident::factory()->create([
            'user_id' => $resident2->id,
            'community_id' => $this->community->id,
            'unit_id' => $unit2->id,
            'status' => 'active',
            'current_marker' => true,
        ]);

        $poll->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by_manager' => $this->manager->id,
        ]);

        return $poll;
    }
}
