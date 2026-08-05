<?php

declare(strict_types=1);

namespace Modules\ServiceListing\app\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServiceListingStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The single client-controlled field is the requested target status.
     *
     * Authority fields (community_id, resident_id, closed_at, deleted_at) are
     * intentionally absent from the rules, so validated() can never carry them
     * and closed_at remains server-authoritative. Arbitrary status strings are
     * rejected by the in-rule; only the locked enum values are accepted.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(['active', 'reserved', 'closed'])],
        ];
    }
}
