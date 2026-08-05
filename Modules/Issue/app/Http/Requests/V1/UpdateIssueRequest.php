<?php

declare(strict_types=1);

namespace Modules\Issue\app\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class UpdateIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ->can('update_issue');
    }


    public function rules(): array
    {
        return [
            'category_id' => [
                'sometimes',
                'exists:issue_categories,id',
            ],

            'title' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'description' => [
                'sometimes',
                'string',
            ],

            'location' => [
                'sometimes',
                'string',
                'max:255',
            ],

            'priority' => [
                'sometimes',
                'in:low,medium,high,urgent',
            ],
        ];
    }
}