<?php

declare(strict_types=1);

namespace Modules\ServiceListing\app\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreServiceListingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Only legitimate listing content fields are validated. Authority fields
     * (community_id, resident_id, status, closed_at, deleted_at) are
     * intentionally absent so they can never appear in validated() and are
     * always assigned server-side.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:1', 'max:255'],
            'description' => ['required', 'string', 'min:1', 'max:16000'],
            'type' => ['required', 'string', 'in:sale,rent,share,request'],
            'price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'expires_at' => ['required', 'date', 'after:now'],
        ];
    }
}
