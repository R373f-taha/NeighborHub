<?php

declare(strict_types=1);

namespace Modules\Media\app\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Modules\Media\app\Models\Media;

class StoreMediaRequest extends FormRequest
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
            // `image` + `mimetypes` inspect the uploaded file's actual content
            // (finfo), not the browser claim, so a renamed non-image is rejected.
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpg,jpeg,png,webp,gif',
                'mimetypes:' . implode(',', Media::ALLOWED_MIMES),
                'max:' . Media::MAX_FILE_KB,
            ],
            'position' => ['sometimes', 'integer', 'between:1,' . Media::MAX_POSITION],
        ];
    }
}
