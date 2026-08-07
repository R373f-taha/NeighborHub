<?php

declare(strict_types=1);

namespace Modules\Issue\app\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;
 use Modules\Auth\app\Models\User;


class AssignIssueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()
  && $this->user()->can('assign_issue');    }

public function rules(): array
{
    return [

        'provider_id' => [

            'required',

            'exists:users,id',

            function ($attribute, $value, $fail) {


                $user = User::find($value);


                if (!$user || !$user->hasRole('provider')) {

                    $fail('Selected user is not a provider.');
}
             },

        ],

    ];
}
}