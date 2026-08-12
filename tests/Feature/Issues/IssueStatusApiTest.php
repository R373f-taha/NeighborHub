<?php

declare(strict_types=1);

namespace Tests\Feature\Issues;

use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Models\IssueStatusLog;

/**
 * Issue STATUS API: authorized status transitions, forbidden transitions,
 * validation, status-log capture, and authorization.
 */
class IssueStatusApiTest extends IssueTestCase
{
 private function issueWithStatus(string $status): Issue
{
    return Issue::create([
        'community_id' => $this->communityA->id,
        'category_id' => $this->category->id,
        'title' => 'Status issue',
        'description' => 'd',
        'location' => 'l',
        'priority' => 'medium',
        'status' => $status,
        'reported_by' => $this->residentUserA->id,
        'assigned_to' => $this->provider->id,
    ]);
}

    // ── Auth ──

    public function test_anonymous_status_change_is_unauthenticated(): void
    {
        $this->patchJson($this->statusUri($this->communityA, $this->issueA), ['status' => 'closed'])->assertStatus(401);
    }

    public function test_resident_cannot_change_status(): void
    {
        $this->patchJson($this->statusUri($this->communityA, $this->issueA), ['status' => 'closed'], $this->token($this->residentUserA))
            ->assertStatus(403);
    }

    public function test_provider_can_change_status(): void
    {
        $issue = $this->issueWithStatus('open');

        $this->patchJson($this->statusUri($this->communityA, $issue), ['status' => 'assigned'], $this->token($this->provider))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'assigned');
    }

    // ── Valid transitions ──

    public function test_open_can_transition_to_assigned(): void
    {
        $issue = $this->issueWithStatus('open');

        $this->patchJson($this->statusUri($this->communityA, $issue), ['status' => 'assigned'], $this->token($this->managerA))
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'assigned');

        $this->assertSame('assigned', $issue->fresh()->status->value);
    }

    public function test_open_can_transition_to_closed(): void
    {
        $issue = $this->issueWithStatus('open');

        $this->patchJson($this->statusUri($this->communityA, $issue), ['status' => 'closed'], $this->token($this->managerA))
            ->assertStatus(200);

        $this->assertSame('closed', $issue->fresh()->status->value);
    }

    public function test_assigned_can_transition_to_in_progress(): void
    {
        $issue = $this->issueWithStatus('assigned');

        $this->patchJson($this->statusUri($this->communityA, $issue), ['status' => 'in_progress'], $this->token($this->managerA))
            ->assertStatus(200);

        $this->assertSame('in_progress', $issue->fresh()->status->value);
    }

    public function test_assigned_can_transition_to_closed(): void
    {
        $issue = $this->issueWithStatus('assigned');

        $this->patchJson($this->statusUri($this->communityA, $issue), ['status' => 'closed'], $this->token($this->managerA))
            ->assertStatus(200);
    }

    public function test_in_progress_can_transition_to_resolved(): void
    {
        $issue = $this->issueWithStatus('in_progress');

        $this->patchJson($this->statusUri($this->communityA, $issue), ['status' => 'resolved'], $this->token($this->managerA))
            ->assertStatus(200);

        $this->assertSame('resolved', $issue->fresh()->status->value);
    }

    public function test_resolved_can_transition_to_closed(): void
    {
        $issue = $this->issueWithStatus('resolved');

        $this->patchJson($this->statusUri($this->communityA, $issue), ['status' => 'closed'], $this->token($this->managerA))
            ->assertStatus(200);

        $this->assertSame('closed', $issue->fresh()->status->value);
    }

    // ── Forbidden transitions ──

    public function test_open_cannot_transition_to_resolved(): void
    {
        $issue = $this->issueWithStatus('open');

        $this->patchJson($this->statusUri($this->communityA, $issue), ['status' => 'resolved'], $this->token($this->managerA))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_closed_is_terminal(): void
    {
        $issue = $this->issueWithStatus('closed');

        $this->patchJson($this->statusUri($this->communityA, $issue), ['status' => 'open'], $this->token($this->managerA))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    public function test_status_validates_enum_value(): void
    {
        $this->patchJson($this->statusUri($this->communityA, $this->issueA), ['status' => 'bogus'], $this->token($this->managerA))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['status']);
    }

    // ── Status log ──

    public function test_status_change_records_a_status_log(): void
    {
        $issue = $this->issueWithStatus('open');

        $this->patchJson($this->statusUri($this->communityA, $issue), ['status' => 'closed', 'note' => 'Fixed.'], $this->token($this->managerA))
            ->assertStatus(200);

        $log = IssueStatusLog::where('issue_id', $issue->id)->latest('id')->first();

        $this->assertNotNull($log);
        $this->assertSame('open', $log->old_status->value);
        $this->assertSame('closed', $log->new_status->value);
        $this->assertSame($this->managerA->id, $log->changed_by, 'log author is the authenticated user');
        $this->assertSame('Fixed.', $log->note);
    }
}
