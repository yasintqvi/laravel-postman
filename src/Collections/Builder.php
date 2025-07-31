<?php

namespace YasinTgh\LaravelPostman\Collections;


class Builder
{
    public function __construct(
        protected RouteGrouper $routeGrouper,
        protected array $config,
    ) {}

    public function build(array $routes): array
    {
        $variables = [
            ['key' => 'base_url', 'value' => $this->config['base_url']]
        ];

        $collection = [
            'info' => $this->buildInfo(),
            'item' => $this->routeGrouper->organize($routes),
            'variable' => $variables
        ];

        if ($this->config['auth']['enabled'] ?? false) {
            $collection['auth'] = $this->buildAuth();
            $collection['variable'] = array_merge(
                $variables,
                $this->buildAuthVariables()
            );
        }

        return $collection;
    }

    protected function buildInfo(): array
    {
        return [
            'name' => $this->config['name'],
            'description' => $this->config['description'],
            'schema' => 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json'
        ];
    }

    protected function buildAuth(): array
    {
        $authConfig = $this->config['auth']['type'];

        switch ($authConfig) {
            case 'bearer':
                return [
                    'type' => 'bearer',
                    'bearer' => [
                        [
                            'key' => 'token',
                            'value' => '{{auth_token}}',
                            'type' => 'string'
                        ]
                    ]
                ];

            case 'basic':
                return [
                    'type' => 'basic',
                    'basic' => [
                        [
                            'key' => 'username',
                            'value' => '{{auth_username}}',
                            'type' => 'string'
                        ],
                        [
                            'key' => 'password',
                            'value' => '{{auth_password}}',
                            'type' => 'string'
                        ]
                    ]
                ];

            case 'api_key':
                return [
                    'type' => 'apikey',
                    'apikey' => [
                        [
                            'key' => 'key',
                            'value' => $authConfig['default']['key_name'] ?? 'X-API-KEY',
                            'type' => 'string'
                        ],
                        [
                            'key' => 'value',
                            'value' => '{{api_key}}',
                            'type' => 'string'
                        ],
                        [
                            'key' => 'in',
                            'value' => $authConfig['location'] ?? 'header',
                            'type' => 'string'
                        ]
                    ]
                ];

            default:
                return [];
        }
    }

    protected function buildAuthVariables(): array
    {
        $variables = [];

        $authConfig = $this->config['auth'];

        switch ($authConfig['type']) {
            case 'bearer':
                $variables[] = [
                    'key' => 'auth_token',
                    'value' => $authConfig['default']['token'] ?? '',
                    'type' => 'string',
                    'description' => 'Bearer token for API authentication'
                ];
                break;

            case 'basic':
                $variables[] = [
                    'key' => 'auth_username',
                    'value' => $authConfig['default']['username'] ?? '',
                    'type' => 'string',
                    'description' => 'Basic Auth username'
                ];
                $variables[] = [
                    'key' => 'auth_password',
                    'value' => $authConfig['default']['password'] ?? '',
                    'type' => 'string',
                    'description' => 'Basic Auth password'
                ];
                break;

            case 'api_key':
                $variables[] = [
                    'key' => 'api_key',
                    'value' => $authConfig['default']['key_value'] ?? '',
                    'type' => 'string',
                    'description' => 'API Key for authentication'
                ];
                if (isset($authConfig['default']['key_name'])) {
                    $variables[] = [
                        'key' => 'api_key_name',
                        'value' => $authConfig['default']['key_name'],
                        'type' => 'string',
                        'description' => 'API Key header name'
                    ];
                }
                break;
        }

        return $variables;
    }
}
