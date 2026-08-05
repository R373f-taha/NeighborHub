<?php

declare(strict_types=1);

namespace Modules\Issue\app\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ->can('create_issue');
    }


    public function rules(): array
    {
        return [
            'category_id' => [
                'required',
                'exists:issue_categories,id',
            ],

            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'description' => [
                'required',
                'string',
            ],

            'location' => [
                'required',
                'string',
                'max:255',
            ],

            'priority' => [
                'required',
                'in:low,medium,high,urgent',
            ],
        ];
    }
}