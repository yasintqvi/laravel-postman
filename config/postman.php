<?php

return [
    'api' => [
        'name' => env('APP_NAME', 'Laravel API'),
        'description' => env('API_DESCRIPTION', 'Laravel API Documentation'),
        'base_url' => env('APP_URL', 'http://localhost'),
    ],

    'routes' => [
        'prefix' => 'api',
        'path' => 'routes/api.php', // 'Modules/*/routes/api.php'
    ],

    'structure' => [
        'folders' => [
            'strategy' => 'prefix', // prefix/controller
            'mapping' => [
                // for example 'user' => 'User'
            ],
        ],
        'name' => [
            'strategy' => 'module_verb_status',
            'available_strategies' => [
                'simple' => '{method} {uri}',
                'controller' => '{controller}@{method}',
                'module_verb' => '[{module}] {verb} {noun}',
                'module_verb_status' => '[{module}] {verb} {noun} ({status})',
            ],
        ],
    ],

    'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json',
    ],

];
