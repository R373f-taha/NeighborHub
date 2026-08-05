<?php

declare(strict_types=1);

namespace Modules\Issue\app\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;


class UpdateIssueStatusRequest extends FormRequest
{

    public function authorize(): bool
    {
        return $this->user()
            ->can('update_issue_status');
    }



    public function rules(): array
    {
        return [

            'status' => [
                'required',
                'in:open,assigned,in_progress,resolved,closed',
            ],


            'note' => [
                'nullable',
                'string',
            ],

        ];
    }

}