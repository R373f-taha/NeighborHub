<?php

declare(strict_types=1);

namespace Modules\Messaging\app\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Core Send Message request.
 *
 * Accepts ONLY content. conversation_id / sender_id / community_id /
 * is_read / read_at / timestamps are NEVER accepted; they are
 * server-authoritative.
 */
class SendMessageRequest extends FormRequest
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
            // 16000 worst-case 4-byte UTF-8 chars = 64000 bytes, safely below
            // MySQL TEXT's 65535-byte limit (mirrors ServiceListing.description).
            'content' => ['required', 'string', 'max:16000'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function validated($key = null, $default = null): array
    {
        // Restrict to ONLY content so any extra client-supplied values
        // (sender_id, conversation_id, community_id, is_read, read_at, ...)
        // are structurally ignored.
        return array_intersect_key(parent::validated($key, $default), ['content' => true]);
    }
}
