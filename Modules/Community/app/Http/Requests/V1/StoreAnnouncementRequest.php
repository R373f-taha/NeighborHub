<?php

declare(strict_types=1);

namespace Modules\Community\app\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{

public function authorize(): bool
{
    return true;
}



    public function rules(): array
    {
        return [


            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'content' => [
                'required',
                'string',
            ],

            'priority' => [
                'required',
                'in:normal,important,urgent',
            ],

            'pinned_until' => [
                'nullable',
                'date',
                'after_or_equal:today',
            ],
        ];
    }
}