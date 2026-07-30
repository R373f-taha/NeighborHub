<?php

declare(strict_types=1);

namespace Modules\Community\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
        // $user = $this->user();
        // $community = $this->route('community');

        // if ($user->isSuperAdmin()) {
        //     return true;
        // }

        // if ($user->isManager()) {
        //     return $community->managers()->where('manager_id', $user->id)->exists();
        // }

        // return false;

        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'city' => 'sometimes|string|max:255',
            'address' => 'sometimes|string',
            'is_active' => 'sometimes|boolean',
            'manager_ids' => 'nullable|array',
            'manager_ids.*' => 'exists:users,id',
        ];
    }
}
