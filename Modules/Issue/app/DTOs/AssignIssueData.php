<?php

declare(strict_types=1);

namespace Modules\Issue\app\DTOs;

use Modules\Issue\app\Http\Requests\V1\AssignIssueRequest;


final readonly class AssignIssueData
{

    public function __construct(
        public int $providerId,
    ) {}



    public static function fromRequest(
        AssignIssueRequest $request
    ): self {

        return new self(

            providerId: $request->integer('provider_id')

        );

    }

    public function toArray(): array
    {

        return [

            'assigned_to' => $this->providerId,

        ];

    }

}