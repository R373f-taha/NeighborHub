<?php

namespace Modules\Reports\app\Http\Controllers;

use Illuminate\Routing\Controller;
use Modules\Reports\app\Services\ReportService;

class ReportsController extends Controller
{
    public function __construct(
        protected ReportService $reportService
    ) {}

    public function issuesSummary($communityId)
    {
        return response()->json(
            $this->reportService->issuesSummary($communityId)
        );
    }

    public function engagement($communityId)
    {
        return response()->json(
            $this->reportService->engagement($communityId)
        );
    }

    public function providers($communityId)
    {
        return response()->json(
            $this->reportService->providers($communityId)
        );
    }

    public function servicesActivity($communityId)
    {
        return response()->json(
            $this->reportService->servicesActivity($communityId)
        );
    }
}