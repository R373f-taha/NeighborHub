<?php

declare(strict_types=1);

namespace Modules\Community\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCommunityRequest extends FormRequest
{
    public function authorize(): bool
    {
      return $this->user() && $this->user()->isSuperAdmin();
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'city' => 'required|string|max:255',
            'address' => 'required|string',
             'total_units' => 'nullable|integer|min:0',
            'manager_ids' => 'nullable|array',
            'manager_ids.*' => 'exists:users,id',
        ];
    }
}
