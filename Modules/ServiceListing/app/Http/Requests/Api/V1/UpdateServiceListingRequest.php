<?php

declare(strict_types=1);

namespace Modules\ServiceListing\app\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateServiceListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Partial-update (PATCH) semantics: each mutable content field is
     * validated only when present. Authority fields (community_id,
     * resident_id, status, closed_at, deleted_at) are absent from the
     * rules entirely, so validated() can never carry them and ownership
     * transfer / status changes via this endpoint are impossible.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'string', 'min:1', 'max:255'],
            'description' => ['sometimes', 'string', 'min:1', 'max:16000'],
            'type' => ['sometimes', 'string', 'in:sale,rent,share,request'],
            'price' => ['sometimes', 'nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'expires_at' => ['sometimes', 'date', 'after:now'],
        ];
    }
}
