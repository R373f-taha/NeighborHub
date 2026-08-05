<?php

declare(strict_types=1);

namespace Modules\Messaging\app\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * MSG-5 Mark Conversation Read request.
 *
 * Accepts ONLY an explicit message_id — "advance MY read cursor in this
 * Conversation through THIS Message" (never "mark conversation latest read").
 * The target Message is resolved privacy-safely inside the service; a global
 * exists:messages,id rule is intentionally NOT used (it would disclose private
 * message existence outside this Conversation).
 */
class MarkConversationReadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // authorization is the policy/service responsibility
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'message_id' => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        // Restrict to ONLY message_id so any extra client-supplied values
        // (conversation_id, user_id, last_read_message_id, is_read, ...) are
        // structurally ignored.
        return array_intersect_key(parent::validated($key, $default), ['message_id' => true]);
    }
}
