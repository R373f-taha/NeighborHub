<?php

declare(strict_types=1);

namespace Modules\ServiceListing\app\Exceptions;

use RuntimeException;


class InvalidServiceListingStatusTransitionException extends RuntimeException
{
    public function __construct(private string $from, private string $to)
    {
        parent::__construct("The service listing cannot transition from [{$from}] to [{$to}].");
    }

    public function from(): string
    {
        return $this->from;
    }

    public function to(): string
    {
        return $this->to;
    }
}
