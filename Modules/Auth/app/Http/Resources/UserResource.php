<?php

declare(strict_types=1);

namespace Modules\Auth\app\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Auth\app\Models\User;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'role' => $this->role?->value,
            'is_active' => $this->is_active,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at,
        ];
    }
}
