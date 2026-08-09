<?php

declare(strict_types=1);

namespace Tests\Feature\Poll;

use Modules\Poll\app\Models\PollVote;
use Modules\Poll\app\Services\V1\ChangePollStatusService;
use Modules\Poll\app\Services\V1\VotesManagementService;

/**
 * Core Poll behavior: voting invariants, results aggregation and lifecycle
 * transitions.
 *
 * These exercise the production service classes directly (the Poll HTTP layer
 * is unreachable in production due to the module boot defect) against the
 * intended Poll schema, protecting the real business invariants.
 */
class PollServiceTest extends PollServiceTestCase
{
    // -----------------------------------------------------------------------
    // Voting invariants
    // -----------------------------------------------------------------------

    public function test_active_poll_accepts_first_vote_from_eligible_resident(): void
    {
        [$poll, $optA] = $this->makePoll();
        $service = new VotesManagementService();

        $result = $service->vote($poll, $this->resident, $optA->id);

        $this->assertTrue($result['success']);
        $this->assertSame(1, PollVote::where('poll_id', $poll->id)->count());
        $this->assertDatabaseHas('poll_votes', [
            'poll_id' => $poll->id, 'voter_id' => $this->resident->id, 'option_id' => $optA->id,
        ]);
    }

    public function test_draft_poll_rejects_vote(): void
    {
        [$poll, $optA] = $this->makePoll('draft');

        $result = (new VotesManagementService())->vote($poll, $this->resident, $optA->id);

        $this->assertFalse($result['success']);
        $this->assertSame('Poll is not active.', $result['message']);
    }

    public function test_closed_poll_rejects_vote(): void
    {
        [$poll, $optA] = $this->makePoll('closed');

        $result = (new VotesManagementService())->vote($poll, $this->resident, $optA->id);

        $this->assertFalse($result['success']);
        $this->assertSame('Poll is not active.', $result['message']);
        $this->assertSame(0, PollVote::count());
    }

    public function test_expired_active_poll_rejects_vote(): void
    {
        [$poll, $optA] = $this->makePoll('active', now()->subHour());

        $result = (new VotesManagementService())->vote($poll, $this->resident, $optA->id);

        $this->assertFalse($result['success']);
        $this->assertSame('Poll has expired.', $result['message']);
    }

    public function test_resident_cannot_vote_twice(): void
    {
        [$poll, $optA] = $this->makePoll();
        $service = new VotesManagementService();

        $service->vote($poll, $this->resident, $optA->id);
        $second = $service->vote($poll, $this->resident, $optA->id);

        $this->assertFalse($second['success']);
        $this->assertSame('You have already voted.', $second['message']);
        $this->assertSame(1, PollVote::where('poll_id', $poll->id)->count());
    }

    public function test_option_must_belong_to_the_poll(): void
    {
        [$pollA, $optA] = $this->makePoll();
        [$pollB, $optB] = $this->makePoll(); // different poll

        $result = (new VotesManagementService())->vote($pollA, $this->resident, $optB->id);

        $this->assertFalse($result['success']);
        $this->assertSame('Option does not belong to this poll.', $result['message']);
        $this->assertSame(0, PollVote::count());
    }

    // -----------------------------------------------------------------------
    // Results aggregation
    // -----------------------------------------------------------------------

    public function test_results_aggregate_votes_and_percentages(): void
    {
        [$poll, $optA, $optB] = $this->makePoll();

        PollVote::create(['poll_id' => $poll->id, 'option_id' => $optA->id, 'voter_id' => $this->resident->id, 'submitted_at' => now(), 'voted_at' => now()]);

        $results = (new VotesManagementService())->getResults($poll);

        $this->assertSame($poll->id, $results['poll_id']);
        $this->assertSame(1, $results['total_votes']);

        $yes = collect($results['options'])->firstWhere('text', 'Yes');
        $this->assertSame(1, $yes['votes']);
        $this->assertSame(100.0, (float) $yes['percentage']);

        // Turnout: 1 vote / 1 active current resident.
        $this->assertSame(100.0, (float) $results['turnout']);
    }

    // -----------------------------------------------------------------------
    // Lifecycle transitions
    // -----------------------------------------------------------------------

    public function test_activate_poll_sets_active_status_and_timestamp(): void
    {
        [$poll] = $this->makePoll('draft');

        (new ChangePollStatusService())->activatePoll($poll);

        $this->assertSame('active', $poll->fresh()->status);
        $this->assertNotNull($poll->fresh()->activated_at);
    }

    public function test_close_poll_sets_closed_status_and_timestamp(): void
    {
        [$poll] = $this->makePoll('active');

        (new ChangePollStatusService())->closePoll($poll, $this->creator);

        $this->assertSame('closed', $poll->fresh()->status);
        $this->assertNotNull($poll->fresh()->closed_at);
    }

    public function test_close_expired_polls_closes_only_active_expired_polls(): void
    {
        // An active, expired poll that should be closed.
        [$expired] = $this->makePoll('active', now()->subHour());
        // An active, future poll that must stay open.
        [$future] = $this->makePoll('active', now()->addDay());
        // A closed poll that must remain stable.
        [$alreadyClosed] = $this->makePoll('closed', now()->subHour());

        $count = (new ChangePollStatusService())->closeExpiredPolls();

        $this->assertSame(1, $count);
        $this->assertSame('closed', $expired->fresh()->status);
        $this->assertSame('active', $future->fresh()->status);
        $this->assertSame('closed', $alreadyClosed->fresh()->status);
    }
}
