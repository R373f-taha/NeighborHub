<?php

declare(strict_types=1);

namespace Modules\Messaging\app\Exceptions;

use RuntimeException;


class InvalidConversationReadCursorException extends RuntimeException
{
    public function __construct(string $message = 'The conversation read cursor references an invalid message.', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
