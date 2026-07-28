<?php

declare(strict_types=1);

namespace Modules\Auth\app\Actions;

use Illuminate\Support\Facades\Password;
use Modules\Auth\app\Support\AuthSecurityContext;
use Modules\Auth\app\Support\AuthSecurityLogger;

class SendPasswordResetLinkAction
{
    public function __construct(private readonly AuthSecurityLogger $security) {}

    public function execute(string $email, AuthSecurityContext $securityContext): void
    {
        Password::sendResetLink(['email' => $email]);

        $this->security->passwordResetRequested($email, $securityContext);
    }
}
