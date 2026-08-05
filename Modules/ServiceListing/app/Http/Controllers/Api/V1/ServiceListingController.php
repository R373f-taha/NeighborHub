<?php

declare(strict_types=1);

namespace Modules\ServiceListing\app\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Modules\Community\app\Models\Community;
use Modules\ServiceListing\app\Exceptions\InvalidServiceListingStatusTransitionException;
use Modules\ServiceListing\app\Http\Requests\Api\V1\StoreServiceListingRequest;
use Modules\ServiceListing\app\Http\Requests\Api\V1\UpdateServiceListingRequest;
use Modules\ServiceListing\app\Http\Requests\Api\V1\UpdateServiceListingStatusRequest;
use Modules\ServiceListing\app\Http\Resources\Api\V1\ServiceListingResource;
use Modules\ServiceListing\app\Models\ServiceListing;
use Modules\ServiceListing\app\Services\ServiceListingMutationLogger;
use Modules\ServiceListing\app\Services\ServiceListingService;

class ServiceListingController extends Controller
{
    public function __construct(
        private ServiceListingService $service,
        private ServiceListingMutationLogger $logger,
    ) {}

    public function index(Request $request, Community $community): JsonResponse
    {
        Gate::authorize('viewAny', [ServiceListing::class, $community]);

        $perPage = (int) $request->query('per_page', ServiceListingService::DEFAULT_PER_PAGE);

        return ServiceListingResource::collection(
            $this->service->index($community, $perPage)
        )->additional(['message' => 'Service listings retrieved successfully.'])->response();
    }

    public function show(Community $community, ServiceListing $serviceListing): JsonResponse
    {
        Gate::authorize('view', $serviceListing);

        return response()->json([
            'message' => 'Service listing retrieved successfully.',
            'data' => new ServiceListingResource($this->service->show($serviceListing)),
        ]);
    }

    public function store(StoreServiceListingRequest $request, Community $community): JsonResponse
    {
        Gate::authorize('create', [ServiceListing::class, $community]);

        // The service re-resolves and re-verifies active residency under a
        // row lock inside a transaction (race-safe), so it never trusts a
        // stale Resident snapshot from the policy check above.
        $listing = $this->service->store($request->user(), $community, $request->validated());

        $this->logger->created($request->user(), (int) $community->id, (int) $listing->id);

        return response()->json([
            'message' => 'Service listing created successfully.',
            'data' => new ServiceListingResource($listing),
        ], 201);
    }

    public function update(UpdateServiceListingRequest $request, Community $community, ServiceListing $serviceListing): JsonResponse
    {
        Gate::authorize('update', $serviceListing);

        $listing = $this->service->update($request->user(), $community, $serviceListing, $request->validated());

        $this->logger->updated($request->user(), (int) $community->id, (int) $listing->id);

        return response()->json([
            'message' => 'Service listing updated successfully.',
            'data' => new ServiceListingResource($listing),
        ]);
    }

    public function destroy(Request $request, Community $community, ServiceListing $serviceListing): JsonResponse
    {
        Gate::authorize('delete', $serviceListing);

        $this->service->delete($request->user(), $community, $serviceListing);

        $this->logger->deleted($request->user(), (int) $community->id, (int) $serviceListing->id);

        return response()->json([
            'message' => 'Service listing deleted successfully.',
            'data' => null,
        ], 200);
    }

    /**
     * The Policy is the optimistic HTTP pre-check; the Service is the
     * concurrency authority and re-evaluates capability + transition validity
     * from fresh locked DB state. Only the owned domain transition failure is
     * mapped (to 422); all other errors surface as their natural status.
     */
    public function updateStatus(UpdateServiceListingStatusRequest $request, Community $community, ServiceListing $serviceListing): JsonResponse
    {
        Gate::authorize('updateStatus', $serviceListing);

        try {
            $listing = $this->service->updateStatus(
                $request->user(),
                $community,
                $serviceListing,
                $request->validated()['status'],
            );
        } catch (InvalidServiceListingStatusTransitionException $e) {
            throw ValidationException::withMessages([
                'status' => $e->getMessage(),
            ]);
        }

        return response()->json([
            'message' => 'Service listing status updated successfully.',
            'data' => new ServiceListingResource($listing),
        ], 200);
    }
}
