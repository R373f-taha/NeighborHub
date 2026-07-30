<?php

declare(strict_types=1);

namespace Modules\Community\app\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class ReactAnnouncementRequest extends FormRequest
{

    public function authorize(): bool
    {
        return $this->user()?->isResident() ?? false;
    }



    public function rules(): array
    {
        return [
            'type' => [
                'required',
                'in:like,love,support,helpful,celebrate',
            ],
        ];
    }
}