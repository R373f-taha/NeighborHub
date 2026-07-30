<?php

declare(strict_types=1);

namespace Modules\Interaction\app\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Modules\Community\app\Models\Community;
use Modules\Interaction\app\Enums\ReactionType;
use Modules\Interaction\app\Http\Requests\Api\V1\StoreReactionRequest;
use Modules\Interaction\app\Http\Resources\Api\V1\ReactionResource;
use Modules\Interaction\app\Services\ReactionService;
use Modules\Post\app\Models\Post;

class ReactionController extends Controller
{
    public function __construct(private ReactionService $service) {}

    public function react(StoreReactionRequest $request, Community $community, Post $post): JsonResponse
    {
        Gate::authorize('react', $post);

        $type = ReactionType::from($request->validated('type'));
        $result = $this->service->toggle($post, $request->user(), $type);

        $status = $result['action'] === 'created' ? 201 : 200;

        return ReactionResource::fromToggle($result)
            ->response()
            ->setStatusCode($status);
    }
}

