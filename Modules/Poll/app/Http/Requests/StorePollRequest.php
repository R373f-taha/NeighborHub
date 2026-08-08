<?php

declare(strict_types=1);

namespace Modules\Poll\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Community\app\Models\Community;

class StorePollRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $community = $this->route('communityId') ? Community::find($this->route('communityId')) : null;

        if (!$user) {
            return false;
        }

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
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'ends_at' => 'required|date|after:now',
            'options' => 'required|array|min:2|max:10',
            'options.*' => 'required|string|max:255|distinct',
        ];
    }

    public function messages(): array
    {
         return [
            'title.required' => 'Poll title is required.',
            'title.max' => 'Poll title must not exceed 255 characters.',
            
            'ends_at.required' => 'End date is required.',
            'ends_at.after' => 'End date must be after the current date and time.',

            'options.required' => 'At least two options are required.',
            'options.min' => 'At least two options are required.',
            'options.*.required' => 'Each option must have a label.',
            'options.*.max' => 'Each option must not exceed 255 characters.',
        ];
}
}
