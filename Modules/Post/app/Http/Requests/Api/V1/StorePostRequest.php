<?php

declare(strict_types=1);

namespace Modules\Post\app\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePostRequest extends FormRequest
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
            'category' => ['required', 'string', Rule::in($this->allowedCategories())],
            'content' => ['required', 'string', 'min:1', 'max:10000'],
        ];
    }

    /**
     * @return list<string>
     */
    protected function allowedCategories(): array
    {
        return ['general', 'lost_found', 'question', 'event', 'recommendation'];
    }
}
