<?php

declare(strict_types=1);

namespace Modules\Issue\app\Rules;

use Illuminate\Validation\ValidationException;

class IssueStatusTransitionRule
{

    public static function validate(
        mixed $oldStatus,
        mixed $newStatus
    ): void {

        $old = $oldStatus->value ?? $oldStatus;

        $new = $newStatus->value ?? $newStatus;


        $allowed = [

            'open' => [
                'assigned',
                'closed',
            ],

            'assigned' => [
                'in_progress',
                'closed',
            ],

            'in_progress' => [
                'resolved',
            ],

            'resolved' => [
                'closed',
            ],

            'closed' => [],

        ];



        if (
            !isset($allowed[$old])
            ||
            !in_array($new, $allowed[$old], true)
        ) {

            throw ValidationException::withMessages([

                'status' =>
                    "Cannot change issue status from {$old} to {$new}"

            ]);

        }

    }

}