<?php

declare(strict_types=1);

namespace Modules\Community\app\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnouncementRequest extends FormRequest
{

public function authorize(): bool
{
    return auth()->check();
}



    public function rules(): array
    {
        return [

            'title' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'content' => [
                'sometimes',
                'string',
            ],

            'priority' => [
                'sometimes',
                'in:normal,important,urgent',
            ],

            'pinned_until' => [
                'nullable',
                'date',
            ],
        ];
    }
}