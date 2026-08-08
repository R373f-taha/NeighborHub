<?php

declare(strict_types=1);

namespace Tests\Feature\Issues;

/**
 * Issue ASSIGNMENT API: authorized assignment, validation, side effects,
 * and community scoping.
 */
class IssueAssignmentApiTest extends IssueTestCase
{
    public function test_anonymous_assign_is_unauthenticated(): void
    {
        $this->patchJson($this->assignUri($this->communityA, $this->issueA), ['provider_id' => $this->provider->id])->assertStatus(401);
    }

    public function test_resident_cannot_assign_issue(): void
    {
        $this->patchJson($this->assignUri($this->communityA, $this->issueA), ['provider_id' => $this->provider->id], $this->token($this->residentUserA))
            ->assertStatus(403);
    }

    public function test_provider_cannot_assign_issue(): void
    {
        $this->patchJson($this->assignUri($this->communityA, $this->issueA), ['provider_id' => $this->provider->id], $this->token($this->provider))
            ->assertStatus(403);
    }

    public function test_manager_of_another_community_cannot_assign(): void
    {
        $this->patchJson($this->assignUri($this->communityA, $this->issueA), ['provider_id' => $this->provider->id], $this->token($this->managerB))
            ->assertStatus(403);
    }

    public function test_manager_can_assign_provider_and_status_becomes_assigned(): void
    {
        $this->patchJson($this->assignUri($this->communityA, $this->issueA), ['provider_id' => $this->provider->id], $this->token($this->managerA))
            ->assertStatus(200)
            ->assertJsonPath('data.assigned_to.id', $this->provider->id)
            ->assertJsonPath('data.status', 'assigned');

        $this->assertSame($this->provider->id, $this->issueA->fresh()->assigned_to);
        $this->assertSame('assigned', $this->issueA->fresh()->status->value);
    }

    public function test_assign_validates_provider_id_required(): void
    {
        $this->patchJson($this->assignUri($this->communityA, $this->issueA), [], $this->token($this->managerA))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['provider_id']);
    }

    public function test_assign_validates_provider_id_exists(): void
    {
        $this->patchJson($this->assignUri($this->communityA, $this->issueA), ['provider_id' => 999999], $this->token($this->managerA))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['provider_id']);
    }

    public function test_assign_rejects_issue_from_other_community(): void
    {
        // Exposes a production defect: assign resolves the issue by global id
        // while ManagerMiddleware only validates the URL community, so a manager
        // of community A can assign an issue belonging to community B.
        $response = $this->patchJson($this->assignUri($this->communityA, $this->issueB), ['provider_id' => $this->provider->id], $this->token($this->managerA));

        $this->assertSame(404, $response->status(), 'PRODUCTION DEFECT: cross-community issue is assigned instead of 404.');
    }
}
