<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'mailgun' => [
        'domain' => env('MAILGUN_DOMAIN'),
        'secret' => env('MAILGUN_SECRET'),
        'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
    ],

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'service_gateway' => [
        'token' => env('SERVICE_GATEWAY_TOKEN'),
    ],

    'internal_api' => [
        'default_base_url' => env('INTERNAL_API_BASE_URL', rtrim((string) env('APP_URL', 'http://localhost'), '/') . '/api'),
        'timeout' => (int) env('INTERNAL_API_TIMEOUT', 4),
        'connect_timeout' => (int) env('INTERNAL_API_CONNECT_TIMEOUT', 2),
        'endpoints' => [
            'hr' => env('HR_API_BASE_URL'),
            'payroll' => env('PAYROLL_API_BASE_URL'),
            'attendance' => env('ATTENDANCE_API_BASE_URL'),
            'recruitment' => env('RECRUITMENT_API_BASE_URL'),
            'training' => env('TRAINING_API_BASE_URL'),
            'reporting' => env('REPORTING_API_BASE_URL'),
            'chatbot' => env('CHATBOT_API_BASE_URL'),
        ],
    ],

];
