<?php

namespace Modules\Community\app\Http\Requests;

class myResidencyRequest extends JoinCommunityRequest
{
    public function rules(): array
    {
        return [
            'residency_id' => 'required|exists:users,id'
        ];
    }
}
