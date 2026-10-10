<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication API Configuration
    |--------------------------------------------------------------------------
    |
    | This configuration file contains all the time-based limits and TTLs
    | for the authentication API v2. These values can be overridden
    | via environment variables.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Token Expiration Times
    |--------------------------------------------------------------------------
    */

    'access_token_ttl_minutes' => env('AUTH_API_ACCESS_TOKEN_TTL_MINUTES', 1440),

    'refresh_token_ttl_days' => env('AUTH_API_REFRESH_TOKEN_TTL_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | OTP Configuration
    |--------------------------------------------------------------------------
    */

    'otp_ttl_minutes' => env('AUTH_API_OTP_TTL_MINUTES', 10),

    'otp_max_attempts' => env('AUTH_API_OTP_MAX_ATTEMPTS', 5),

    'otp_resend_delay_seconds' => env('AUTH_API_OTP_RESEND_DELAY_SECONDS', 60),

    /*
    |--------------------------------------------------------------------------
    | Intermediate Token Expiration Times
    |--------------------------------------------------------------------------
    */

    'verification_token_ttl_minutes' => env('AUTH_API_VERIFICATION_TOKEN_TTL_MINUTES', 15),

    'challenge_token_ttl_minutes' => env('AUTH_API_CHALLENGE_TOKEN_TTL_MINUTES', 15),

    'reset_token_ttl_minutes' => env('AUTH_API_RESET_TOKEN_TTL_MINUTES', 15),

    /*
    |--------------------------------------------------------------------------
    | Rate Limits
    |--------------------------------------------------------------------------
    */

    'rate_limits' => [
        'otp_send' => [
            'per_minute' => env('AUTH_API_RATE_OTP_SEND_PER_MINUTE', 1),
            'per_hour' => env('AUTH_API_RATE_OTP_SEND_PER_HOUR', 5),
        ],
        'otp_verify' => [
            'per_minute' => env('AUTH_API_RATE_OTP_VERIFY_PER_MINUTE', 5),
        ],
        'login' => [
            'per_minute' => env('AUTH_API_RATE_LOGIN_PER_MINUTE', 5),
            'per_ip_per_minute' => env('AUTH_API_RATE_LOGIN_PER_IP_PER_MINUTE', 20),
        ],
        'refresh' => [
            'per_minute' => env('AUTH_API_RATE_REFRESH_PER_MINUTE', 60),
        ],
        'sensitive' => [
            'per_minute' => env('AUTH_API_RATE_SENSITIVE_PER_MINUTE', 5),
        ],
    ],

];
