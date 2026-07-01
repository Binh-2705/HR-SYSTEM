<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => explode(',', env('CORS_ALLOWED_METHODS', 'GET,POST,PUT,PATCH,DELETE,OPTIONS')),

    'allowed_origins' => array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:8080,http://127.0.0.1:8080')))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => explode(',', env('CORS_ALLOWED_HEADERS', 'Content-Type,Authorization,X-Requested-With,X-Service-Token,X-Account-Id,X-Account-Role,X-Account-Permissions')),

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

];
