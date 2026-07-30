<?php

declare(strict_types=1);

namespace Modules\Community\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use Modules\Community\app\Models\Unit;

class JoinCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'unit_id' => 'required|exists:units,id',
            'residence_type' => 'required|in:owner,tenant',
        ];
    }

    /**
     * Additional validation: Ensure the unit belongs to the community
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($validator) {
            $unitId = $this->input('unit_id');
            $communityId = $this->route('communityId');//?->id;

            // Debugging line to check the value of $communityId
            // If no community in the route
            if (!$communityId) {
                $validator->errors()->add('community', 'Community not found');
                return;
            }

            // Check if the unit belongs to the community
            $unit = Unit::where('id', $unitId)
                ->where('community_id', $communityId)
                ->first();

            if (!$unit) {
                $validator->errors()->add('unit_id', 'The specified unit does not belong to this community');
            }
        });
    }

    public function messages(): array
    {
        return [
            'unit_id.required' => 'Unit ID is required',
            'unit_id.exists' => 'The selected unit does not exist',
            'residence_type.required' => 'Residence type is required',
            'residence_type.in' => 'Residence type must be either owner or tenant',
        ];
    }
}
