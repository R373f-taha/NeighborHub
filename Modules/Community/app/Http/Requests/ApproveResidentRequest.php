<?php

declare(strict_types=1);

namespace Modules\Community\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ApproveResidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $community = $this->route('community');

        if ($user->isSuperAdmin()) {
            return true;
        }

        if ($user->isManager()) {
            return $community->managers()->where('manager_id', $user->id)->exists();
        }

        return false;
    }

    public function rules(): array
    {
        return [
            'status' => 'sometimes|in:active,suspended,rejected',
        ];
    }
}
