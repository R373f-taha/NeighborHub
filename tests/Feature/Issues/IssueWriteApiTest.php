<?php

declare(strict_types=1);

namespace Tests\Feature\Issues;

use Modules\Issue\app\Models\Issue;

/**
 * Issue WRITE API: store/update/delete authorization, validation,
 * mass-assignment protection, and server-controlled fields.
 */
class IssueWriteApiTest extends IssueTestCase
{
    private array $validPayload;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validPayload = ['category_id' => $this->category->id, 'title' => 'Broken elevator', 'description' => 'Not working.', 'location' => 'Block C', 'priority' => 'high'];
    }

    // ── Store ──

    public function test_anonymous_store_is_unauthenticated(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->validPayload)->assertStatus(401);
    }

    public function test_manager_cannot_create_issue_because_creation_is_resident_only(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->validPayload, $this->token($this->managerA))
            ->assertStatus(403);
    }

    public function test_super_admin_cannot_create_issue_because_creation_is_resident_only(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->validPayload, $this->token($this->superAdmin))
            ->assertStatus(403);
    }

    public function test_provider_cannot_create_issue(): void
    {
        $this->postJson($this->indexUri($this->communityA), $this->validPayload, $this->token($this->provider))
            ->assertStatus(403);
    }

    public function test_resident_can_create_issue(): void
    {
        $response = $this->postJson($this->indexUri($this->communityA), $this->validPayload, $this->token($this->residentUserA))
            ->assertStatus(201);

        $created = Issue::latest('id')->first();
        $this->assertNotNull($created);
        $this->assertSame($response->json('data.id'), $created->id);
        $this->assertSame($this->communityA->id, $created->community_id, 'community is server-controlled from the route');
        $this->assertSame($this->residentUserA->id, $created->reported_by, 'reporter is server-controlled from the authenticated user');
        $this->assertSame('open', $created->status->value, 'status defaults to open');
        $this->assertNull($created->assigned_to, 'assignee is not set on creation');
    }

    public function test_store_validates_required_fields(): void
    {
        $this->postJson($this->indexUri($this->communityA), [], $this->token($this->residentUserA))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category_id', 'title', 'description', 'location', 'priority']);
    }

    public function test_store_validates_priority_enum(): void
    {
        $this->postJson($this->indexUri($this->communityA), array_replace($this->validPayload, ['priority' => 'instant']), $this->token($this->residentUserA))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_store_validates_category_exists(): void
    {
        $this->postJson($this->indexUri($this->communityA), array_replace($this->validPayload, ['category_id' => 999999]), $this->token($this->residentUserA))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category_id']);
    }

    public function test_store_validates_title_length(): void
    {
        $this->postJson($this->indexUri($this->communityA), array_replace($this->validPayload, ['title' => str_repeat('x', 256)]), $this->token($this->residentUserA))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    }

    public function test_store_ignores_spoofed_server_controlled_fields(): void
    {
        $spoof = array_replace($this->validPayload, [
            'community_id' => $this->communityB->id,
            'reported_by' => $this->residentUserB->id,
            'status' => 'closed',
            'assigned_to' => $this->provider->id,
        ]);

        $this->postJson($this->indexUri($this->communityA), $spoof, $this->token($this->residentUserA))
            ->assertStatus(201);

        $created = Issue::latest('id')->first();
        $this->assertSame($this->communityA->id, $created->community_id);
        $this->assertSame($this->residentUserA->id, $created->reported_by);
        $this->assertSame('open', $created->status->value);
        $this->assertNull($created->assigned_to);
    }

    // ── Update ──

    public function test_anonymous_update_is_unauthenticated(): void
    {
        $this->putJson($this->showUri($this->communityA, $this->issueA), ['title' => 'Changed'])->assertStatus(401);
    }

    public function test_provider_cannot_update_issue(): void
    {
        $this->putJson($this->showUri($this->communityA, $this->issueA), ['title' => 'Changed'], $this->token($this->provider))
            ->assertStatus(403);
    }

    public function test_update_validates_priority_enum(): void
    {
        $this->putJson($this->showUri($this->communityA, $this->issueA), ['priority' => 'instant'], $this->token($this->managerA))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['priority']);
    }

    public function test_update_persists_changes_to_unassigned_issue(): void
    {
        // Exposes a production defect: the update route uses implicit
        // {issue} route-model binding which resolves to an empty model, so the
        // controller never loads the real issue, never persists changes, and
        // returns a null-data resource.
        $this->putJson($this->showUri($this->communityA, $this->issueA), ['title' => 'Updated title'], $this->token($this->managerA));

        $this->assertSame('Updated title', $this->issueA->fresh()->title, 'PRODUCTION DEFECT: issue update does not persist (broken route binding).');
    }

    public function test_update_returns_404_for_unknown_issue(): void
    {
        // Exposes the same binding defect: an unknown issue id does not resolve
        // to a 404 because the binding injects an empty model.
        $response = $this->putJson($this->showUri($this->communityA, 999999), ['title' => 'X'], $this->token($this->managerA));

        $this->assertSame(404, $response->status(), 'PRODUCTION DEFECT: unknown issue returns 200 (empty resource) instead of 404.');
    }

    // ── Delete ──

    public function test_anonymous_delete_is_unauthenticated(): void
    {
        $this->deleteJson($this->showUri($this->communityA, $this->issueA))->assertStatus(401);
    }

    public function test_resident_cannot_delete_issue(): void
    {
        $this->deleteJson($this->showUri($this->communityA, $this->issueA), [], $this->token($this->residentUserA))
            ->assertStatus(403);
    }

    public function test_manager_of_another_community_cannot_delete(): void
    {
        $this->deleteJson($this->showUri($this->communityA, $this->issueA), [], $this->token($this->managerB))
            ->assertStatus(403);
    }

    public function test_manager_of_community_can_delete(): void
    {
        $this->deleteJson($this->showUri($this->communityA, $this->issueA), [], $this->token($this->managerA))
            ->assertStatus(200)
            ->assertJsonPath('message', 'Issue deleted successfully');

        $this->assertSoftDeleted('issues', ['id' => $this->issueA->id]);
    }

    public function test_delete_rejects_issue_from_other_community(): void
    {
        // Exposes a production defect: destroy resolves the issue by global id
        // (Issue::findOrFail) and the ManagerMiddleware only checks the URL
        // community, so a manager of community A can soft-delete an issue that
        // belongs to community B through community A's route.
        $response = $this->deleteJson($this->showUri($this->communityA, $this->issueB), [], $this->token($this->managerA));

        $this->assertSame(404, $response->status(), 'PRODUCTION DEFECT: cross-community issue is deleted instead of 404.');
    }
}
