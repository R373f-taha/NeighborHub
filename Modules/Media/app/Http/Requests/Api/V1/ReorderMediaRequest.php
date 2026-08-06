<?php

declare(strict_types=1);

namespace Modules\Media\app\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Media\app\Models\Media;

class ReorderMediaRequest extends FormRequest
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
            'items' => ['required', 'array', 'max:' . Media::MAX_PER_PARENT],
            'items.*.id' => ['required', 'integer'],
            'items.*.position' => ['required', 'integer', 'between:1,' . Media::MAX_POSITION],
        ];
    }
}
