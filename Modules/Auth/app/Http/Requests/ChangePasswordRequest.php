<?php

declare(strict_types=1);

namespace Modules\Auth\app\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class ChangePasswordRequest extends FormRequest
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
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', Password::min(15)->max(64)],
            'password_confirmation' => ['required', 'string'],
        ];
    }

    /**
     * @param  Validator  $validator
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $current = $this->input('current_password');
            $new = $this->input('password');

            if (is_string($current) && is_string($new) && $current === $new) {
                $validator->errors()->add('password', 'The new password must be different from your current password.');
            }
        });
    }
}
