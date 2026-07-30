<?php

return [
    'name' => 'Auth',

    'token_expiration' => (int) env('AUTH_TOKEN_EXPIRATION_MINUTES', 60 * 24 * 30),

    'password_reset_url' => env('AUTH_PASSWORD_RESET_URL'),

    'security_log_dedup_store' => env('AUTH_SECURITY_LOG_DEDUP_STORE', 'redis'),
];
