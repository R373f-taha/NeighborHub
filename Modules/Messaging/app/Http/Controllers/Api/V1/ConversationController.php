<?php

declare(strict_types=1);

namespace Modules\Messaging\app\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Community\app\Models\Community;
use Modules\Messaging\app\Http\Requests\Api\V1\MarkConversationReadRequest;
use Modules\Messaging\app\Http\Requests\Api\V1\SendMessageRequest;
use Modules\Messaging\app\Http\Resources\Api\V1\ConversationResource;
use Modules\Messaging\app\Http\Resources\Api\V1\MessageResource;
use Modules\Messaging\app\Http\Resources\Api\V1\ReadStateResource;
use Modules\Messaging\app\Models\Conversation;
use Modules\Messaging\app\Services\ConversationAccessService;
use Modules\Messaging\app\Services\ConversationMutationLogger;
use Modules\Messaging\app\Services\ConversationReadStateService;
use Modules\Messaging\app\Services\ConversationService;
use Modules\Messaging\app\Services\MessageService;

class ConversationController extends Controller
{
    public function __construct(
        private ConversationService $service,
        private ConversationAccessService $access,
        private MessageService $messages,
        private ConversationReadStateService $readState,
        private ConversationMutationLogger $logger,
    ) {}

    /**
     * List the authenticated user's active Conversations in this Community.
     */
    public function index(Request $request, Community $community): JsonResponse
    {
        Gate::authorize('viewAny', [Conversation::class, $community]);

        $perPage = (int) $request->query('per_page', ConversationService::DEFAULT_PER_PAGE);

        $paginator = $this->service->listForParticipant($community, $request->user(), $perPage);

        // Attach a DERIVED unread_count to each conversation with a single
        // set-based query (no N+1). GET remains side-effect free.
        $this->readState->attachUnreadCounts($paginator, $request->user());

        return ConversationResource::collection($paginator)
            ->additional(['message' => 'Conversations retrieved successfully.'])->response();
    }

    /**
     * List Message history for an accessible Conversation.
     *
     * {conversation} is received as a raw integer and resolved through the
     * privacy-safe scoped query. Any miss (missing / foreign-community /
     * non-participant / left) becomes a uniform 404 before authorization,
     * so private-conversation existence is never disclosed.
     */
    public function messages(Request $request, Community $community, int $conversation): JsonResponse
    {
        $resolved = $this->service->resolveForParticipant($community, $request->user(), (int) $conversation);

        if ($resolved === null) {
            abort(404);
        }

        Gate::authorize('viewMessages', $resolved);

        $participant = $this->access->findActiveParticipant($request->user(), $resolved);

        // Guaranteed by resolveForParticipant, but kept defensive.
        if ($participant === null) {
            abort(404);
        }

        $perPage = (int) $request->query('per_page', ConversationService::DEFAULT_PER_PAGE);

        return MessageResource::collection(
            $this->service->messagesForParticipant($resolved, $participant, $perPage)
        )->additional(['message' => 'Messages retrieved successfully.'])->response();
    }

    /**
     * Send a Message into an accessible Conversation.
     *
     * {conversation} is received as a raw integer and resolved through the
     * privacy-safe locked query inside MessageService. Any miss (missing /
     * wrong-community / non-participant / left actor) becomes a uniform 404.
     * An archived/closed Conversation cannot accept messages (422).
     */
    public function store(SendMessageRequest $request, Community $community, int $conversation): JsonResponse
    {
        Gate::authorize('sendMessage', [Conversation::class, $community]);

        $message = $this->messages->send(
            $request->user(),
            $community,
            (int) $conversation,
            $request->validated()['content'],
        );

        // Audit AFTER commit so no DB lock is held during logging I/O.
        $this->logger->messageSent($request->user(), (int) $community->id, (int) $message->conversation_id, (int) $message->id);

        $message->load('sender:id,name');

        return response()->json([
            'message' => 'Message sent successfully.',
            'data' => new MessageResource($message),
        ], 201);
    }

    /**
     * Advance the authenticated participant's read cursor through an explicit
     * Message in an accessible Conversation.
     *
     * {conversation} is received as a raw integer and resolved through the
     * privacy-safe locked query inside ConversationReadStateService. Any miss
     * (missing / wrong-community / non-participant / left actor) becomes a
     * uniform 404. No Resident requirement, valid for any readable type/status.
     */
    public function readUpdate(MarkConversationReadRequest $request, Community $community, int $conversation): JsonResponse
    {
        Gate::authorize('markRead', [Conversation::class, $community]);

        $messageId = (int) $request->validated()['message_id'];

        $data = $this->readState->markRead($request->user(), $community, (int) $conversation, $messageId);

        return response()->json([
            'message' => 'Conversation read state updated successfully.',
            'data' => new ReadStateResource($data),
        ]);
    }
}
