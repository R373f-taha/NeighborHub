<?php

declare(strict_types=1);

namespace Modules\Issue\app\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class AssignIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
            ->can('assign_issue');
    }


    public function rules(): array
    {
        return [
            'provider_id' => [
                'required',
                'exists:users,id',
            ],
        ];
    }
}