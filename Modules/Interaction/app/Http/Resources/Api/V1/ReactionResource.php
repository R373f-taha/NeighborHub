<?php

declare(strict_types=1);

namespace Modules\Interaction\app\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class ReactionResource extends JsonResource
{
    private string $action;
    private int $reactionsCount;

    public static function fromToggle(array $result): self
    {
        if ($result['reaction'] === null) {
            $resource = new self(null);
        } else {
            $resource = new self($result['reaction']);
        }

        $resource->action = $result['action'];
        $resource->reactionsCount = $result['reactions_count'];

        return $resource;
    }

    public function toArray($request): array
    {
        return [
            'action' => $this->action,
            'reaction' => $this->resource ? [
                'id' => $this->resource->id,
                'type' => $this->resource->type instanceof \BackedEnum
                    ? $this->resource->type->value
                    : $this->resource->type,
            ] : null,
            'reactions_count' => $this->reactionsCount,
        ];
    }
}
