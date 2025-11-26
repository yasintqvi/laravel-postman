<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Basic Configuration
    |--------------------------------------------------------------------------
    |
    | Core settings for the API documentation
    |
    */
    'name' => env('APP_NAME', 'Laravel API'),
    'description' => env('API_DESCRIPTION', 'API Documentation'),
    'base_url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Route Filtering Configuration
    |--------------------------------------------------------------------------
    |
    | Define which routes should be included/excluded from documentation
    |
    */
    'routes' => [
        // Base prefix for API routes (e.g. 'api' for routes like 'api/users')
        'prefix' => 'api',

        // Routes to explicitly include
        'include' => [
            // URI patterns to include (supports wildcards)
            'patterns' => [],

            // Only routes with these middleware
            'middleware' => [],

            // Only routes from these controllers
            'controllers' => [],
        ],

        // Routes to explicitly exclude
        'exclude' => [
            // URI patterns to exclude (supports wildcards)
            'patterns' => [],

            // Exclude routes with these middleware
            'middleware' => [],

            // Exclude routes from these controllers
            'controllers' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Documentation Structure
    |--------------------------------------------------------------------------
    |
    | How the documentation should be organized in Postman
    |
    */
    'structure' => [
        'folders' => [
            // Grouping strategy: 'prefix', 'nested_path', 'controller'
            'strategy' => 'nested_path',
            'max_depth' => 10, //  when strategy is nested_path

            // Custom name mapping for folders
            'mapping' => [
                // Example: 'admin' => 'Administration'
            ],
        ],

        /**
         * Postman request naming format.
         * Placeholders: {method}, {uri}, {controller}, {action}
         * Example: '[POST] /users' or 'UserController@store'
         */
        'naming_format' => '[{method}] {uri}',

        /**
         * Request body settings:
         * - default_body_type: 'raw' or 'formdata'
         * - default_values: preset values applied to generated request fields
         */
        'requests' => [
            'default_body_type' => 'raw',
            'default_values' => [
                'cell_number' => '09121234567',            
                'otp_code' => '1234',
                'shift_days' => [],
                'shift_days.*.day_of_week' => '0',
                'shift_days.*.is_holiday' => '1',
                'shift_days.*.is_midnight' => '0',
                'shift_days.*.subshifts' => [],
                'shift_days.*.subshifts.*.start_time' => '09:00',
                'shift_days.*.subshifts.*.end_time' => '17:00',
                'shift_days.*.subshifts.*.permitted_start_time' => '08:45',
                'shift_days.*.subshifts.*.permitted_end_time' => '17:30',
                'shift_days.*.subshifts.*.start_overtime_time' => '18:00',
                'shift_days.*.subshifts.*.has_overtime' => '1',
                'shift_days.*.subshifts.*.start_overtime_after_end' => '0',
                'shift_days.*.subshifts.*.is_advanced' => '1',
                'shift_days.*.subshifts.*.coverable_delay' => '0',
                'shift_days.*.subshifts.*.has_start_overtime' => '0'
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for API authentication documentation and examples
    |
    | Determines how authentication is handled in the generated documentation,
    | including auth type detection, protected route identification, and
    | example values for documentation purposes.
    |
    */
    'auth' => [
        // Enable authentication documentation
        'enabled' => false,

        // Supported: 'bearer', 'basic', 'api_key'
        'type' => 'bearer',

        // Where to send the auth: 'header' or 'query'
        'location' => 'header',

        // Default values (use env vars for real values)
        'default' => [
            'token' => 'your-access-token',       // For bearer auth
            'username' => 'user@example.com',      // For basic auth
            'password' => 'password',              // For basic auth
            'key_name' => 'X-API-KEY',             // For api_key auth
            'key_value' => 'your-api-key-here',    // For api_key auth
        ],

        // Middleware that indicate protected routes
        'protected_middleware' => ['auth:api'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Headers
    |--------------------------------------------------------------------------
    |
    | Headers to include with every request
    |
    */
    'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ],

    /*
    |--------------------------------------------------------------------------
    | Output Configuration
    |--------------------------------------------------------------------------
    |
    | Where and how to save the generated documentation
    |
    */
    'output' => [
        'driver' => env('POSTMAN_STORAGE_DISK', 'local'),

        // Storage path for generated files
        'path' => env('POSTMAN_STORAGE_DIR', storage_path('postman')),

        // File naming pattern (date will be appended)
        'filename' => env('POSTMAN_STORAGE_FILE', 'api_collection'),
    ],
];
