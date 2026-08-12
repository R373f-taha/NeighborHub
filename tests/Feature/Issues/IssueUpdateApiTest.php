<?php

declare(strict_types=1);

namespace Tests\Feature\Issues;

use Modules\Issue\app\Models\Issue;
use Modules\Issue\app\Models\IssueStatusLog;

/**
 * Issue UPDATES API: adding progress notes (status-log entries), listing
 * them, authorization, validation, server-controlled authorship, and ordering.
 */
class IssueUpdateApiTest extends IssueTestCase
{
    // ── Add update ──

    public function test_anonymous_add_update_is_unauthenticated(): void
    {
        $this->postJson($this->updatesUri($this->communityA, $this->issueA), ['note' => 'Working on it'])->assertStatus(401);
    }

    public function test_resident_cannot_add_update(): void
    {
        $this->postJson($this->updatesUri($this->communityA, $this->issueA), ['note' => 'Working on it'], $this->token($this->residentUserA))
            ->assertStatus(403);
    }

    public function test_manager_can_add_update(): void
    {
        $this->postJson($this->updatesUri($this->communityA, $this->issueA), ['note' => 'Working on it'], $this->token($this->managerA))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Issue update added successfully');

        $log = IssueStatusLog::where('issue_id', $this->issueA->id)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame('Working on it', $log->note);
        $this->assertSame($this->managerA->id, $log->changed_by);
    }

   public function test_provider_can_add_update(): void
{
    $this->issueA->update([
        'assigned_to' => $this->provider->id,
        'status' => 'assigned',
    ]);

    $this->postJson(
        $this->updatesUri($this->communityA, $this->issueA),
        ['note' => 'On site.'],
        $this->token($this->provider)
    )->assertStatus(200);
}

    public function test_add_update_validates_note_required(): void
    {
        $this->postJson($this->updatesUri($this->communityA, $this->issueA), [], $this->token($this->managerA))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['note']);
    }

    public function test_add_update_author_is_server_controlled(): void
    {
        $this->postJson($this->updatesUri($this->communityA, $this->issueA), ['note' => 'Note', 'changed_by' => $this->residentUserA->id], $this->token($this->managerA))
            ->assertStatus(200);

        $log = IssueStatusLog::where('issue_id', $this->issueA->id)->latest('id')->first();
        $this->assertSame($this->managerA->id, $log->changed_by, 'changed_by is server-controlled and ignores client input');
    }

    // ── List updates ──

    public function test_list_returns_updates_as_collection(): void
    {
        $this->postJson($this->updatesUri($this->communityA, $this->issueA), ['note' => 'First'], $this->token($this->managerA));
        $this->postJson($this->updatesUri($this->communityA, $this->issueA), ['note' => 'Second'], $this->token($this->managerA));

        $response = $this->getJson($this->updatesUri($this->communityA, $this->issueA), $this->token($this->residentUserA))
            ->assertStatus(200);

        $items = $response->json();
        $this->assertIsArray($items);
        $this->assertCount(2, $items);
    }

    public function test_list_is_ordered_newest_first(): void
    {
        $this->postJson($this->updatesUri($this->communityA, $this->issueA), ['note' => 'Older'], $this->token($this->managerA));
        $this->postJson($this->updatesUri($this->communityA, $this->issueA), ['note' => 'Newer'], $this->token($this->managerA));

        $notes = collect($this->getJson($this->updatesUri($this->communityA, $this->issueA), $this->token($this->residentUserA))->json())->pluck('note')->all();

        $this->assertSame(['Newer', 'Older'], $notes);
    }

    public function test_list_serializes_changer_safely(): void
    {
        // Exposes a production defect: the updates list returns the raw
        // IssueStatusLog collection (`response()->json(...)`), serializing the
        // full User model for `changer` (only `password` is hidden). The
        // dedicated IssueStatusLogResource exists but is not applied here, so
        // email / phone / remember_token leak.
        $this->postJson($this->updatesUri($this->communityA, $this->issueA), ['note' => 'Note'], $this->token($this->managerA));

        $items = $this->getJson($this->updatesUri($this->communityA, $this->issueA), $this->token($this->residentUserA))->json();
        $changer = $items[0]['changer'];

        $this->assertSame($this->managerA->id, $changer['id']);
        $this->assertSame($this->managerA->name, $changer['name']);
        $this->assertArrayNotHasKey('password', $changer);
        $this->assertArrayNotHasKey('email', $changer, 'PRODUCTION DEFECT: changer exposes the full user record including email.');
    }

    public function test_list_includes_status_change_logs(): void
    {
        $issue = Issue::create(['community_id' => $this->communityA->id, 'category_id' => $this->category->id, 'title' => 'x', 'description' => 'd', 'location' => 'l', 'priority' => 'low', 'status' => 'open', 'reported_by' => $this->residentUserA->id]);

        $this->patchJson($this->statusUri($this->communityA, $issue), ['status' => 'closed'], $this->token($this->managerA));
        $this->postJson($this->updatesUri($this->communityA, $issue), ['note' => 'Note log'], $this->token($this->managerA));

        $items = $this->getJson($this->updatesUri($this->communityA, $issue), $this->token($this->residentUserA))->json();
        $this->assertCount(2, $items);
    }
}
