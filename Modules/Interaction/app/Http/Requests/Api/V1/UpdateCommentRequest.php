<?php

declare(strict_types=1);

namespace Modules\Interaction\app\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['sometimes', 'string', 'min:1', 'max:5000'],
        ];
    }
}
