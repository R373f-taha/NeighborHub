<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use Modules\Community\app\Models\Community;

/**
 * Authorization matrix for the community analytics (Reports) endpoints.
 *
 * Every report is gated by auth:sanctum + managerOrSuperAdmin, so the same
 * matrix applies to all four report endpoints and is asserted for each one.
 */
class ReportsAuthorizationTest extends ReportsTestCase
{
    public function test_issues_summary_authorization_matrix(): void
    {
        $this->assertAuthorizationMatrix('issues-summary');
    }

    public function test_engagement_authorization_matrix(): void
    {
        $this->assertAuthorizationMatrix('engagement');
    }

    public function test_providers_authorization_matrix(): void
    {
        $this->assertAuthorizationMatrix('providers');
    }

    public function test_services_activity_authorization_matrix(): void
    {
        $this->assertAuthorizationMatrix('services-activity');
    }

    private function assertAuthorizationMatrix(string $endpoint): void
    {
        // Anonymous requests are rejected before any analytics run.
        $this->getJson($this->uri($endpoint, $this->communityA))
            ->assertUnauthorized();

        // Residents are not permitted (manager-or-super-admin only).
        $this->getJson($this->uri($endpoint, $this->communityA), $this->token($this->residentUserA))
            ->assertForbidden();

        // Providers are not permitted either.
        $this->getJson($this->uri($endpoint, $this->communityA), $this->token($this->provider))
            ->assertForbidden();

        // A manager of a different community cannot read this community's reports.
        $this->getJson($this->uri($endpoint, $this->communityA), $this->token($this->managerB))
            ->assertForbidden();

        // A report requested for a community that does not exist is rejected.
        $missing = Community::max('id') + 1;
        $this->getJson("/api/communities/{$missing}/reports/{$endpoint}", $this->token($this->managerA))
            ->assertNotFound();

        // The manager of this community is authorized.
        $this->getJson($this->uri($endpoint, $this->communityA), $this->token($this->managerA))
            ->assertStatus(200);

        // A super admin is authorized for any community.
        $this->getJson($this->uri($endpoint, $this->communityA), $this->token($this->superAdmin))
            ->assertStatus(200);
    }
}
