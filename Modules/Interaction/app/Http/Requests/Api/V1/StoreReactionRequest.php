<?php

declare(strict_types=1);

namespace Modules\Interaction\app\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Modules\Interaction\app\Enums\ReactionType;

class StoreReactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ReactionType::class)],
            'user_id' => ['prohibited'],
            'reactionable_type' => ['prohibited'],
            'reactionable_id' => ['prohibited'],
            'community_id' => ['prohibited'],
            'post_id' => ['prohibited'],
            'resident_id' => ['prohibited'],
        ];
    }
}
