<?php

declare(strict_types=1);

namespace Modules\Issue\app\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;


class AddIssueLogNoteRequest extends FormRequest
{

    public function authorize(): bool
    {
        return $this->user()
            ->can('add_issue_update');
    }



    public function rules(): array
    {
        return [

            'note' => [
                'required',
                'string',
            ],

        ];
    }

}