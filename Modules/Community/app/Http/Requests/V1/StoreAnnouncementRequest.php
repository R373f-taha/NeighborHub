<?php

declare(strict_types=1);

namespace Modules\Community\app\Http\Requests\V1;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{

public function authorize(): bool
{
    return true;
}

// public function authorize(): bool
// {
//     $user = $this->user();

//     if (! $user) {
//         return false;
//     }

//     if ($user->isSuperAdmin()) {
//         return true;
//     }

//     return $user->isManager()
//         && $user->managedCommunities()
//             ->where(
//                 'communities.id',
//                 $this->route('communityId')
//             )
//             ->exists();
// }

// public function authorize(): bool
// {
//     $user = $this->user();

//     if (! $user) {
//         return false;
//     }

//     // SuperAdmin can create announcements everywhere
//     if ($user->isSuperAdmin()) {
//         return true;
//     }

//     // Manager only for communities he manages
//     return $user->isManager()
//         && $user->managedCommunities()
//             ->where(
//                 'communities.id',
//                 $this->community_id
//             )
//             ->exists();
// }


    public function rules(): array
    {
        return [


            'title' => [
                'required',
                'string',
                'max:255',
            ],

            'content' => [
                'required',
                'string',
            ],

            'priority' => [
                'required',
                'in:normal,important,urgent',
            ],

            'pinned_until' => [
                'nullable',
                'date',
                'after_or_equal:today',
            ],
        ];
    }
}