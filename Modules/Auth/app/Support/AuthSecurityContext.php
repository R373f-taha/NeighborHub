<?php

declare(strict_types=1);

namespace Modules\Auth\app\Support;

final readonly class AuthSecurityContext
{
    public function __construct(
        public ?string $ip,
        public ?string $userAgent,
    ) {}
}
