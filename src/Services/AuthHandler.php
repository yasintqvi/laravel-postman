<?php

namespace YasinTgh\LaravelPostman\Services;

class AuthHandler
{
    public function __construct(protected array $config) {}

    public function getAuthConfig(): array
    {
        return [
            'type' => $this->config['auth']['type'] ?? null,
            'enabled' => $this->config['auth']['enabled'] ?? false,
            'default' => $this->config['auth']['default'] ?? [],
            'location' => $this->config['auth']['location'] ?? 'header'
        ];
    }

    public function shouldAddAuth(array $route): bool
    {
        return ($this->config['auth']['enabled'] ?? false)
            && ($route['isProtected'] ?? false);
    }

    public function generateAuthHeader(): array
    {
        $type = $this->config['auth']['type'] ?? 'bearer';
        $defaults = $this->config['auth']['default'] ?? [];

        return match ($type) {
            'bearer' => [
                'key' => 'Authorization',
                'value' => 'Bearer ' . $defaults['token'] ?? '',
                'type' => 'text'
            ],
            'basic' => [
                'key' => 'Authorization',
                'value' => 'Basic ' . base64_encode(
                    ($defaults['username'] ?? '') . ':' . ($defaults['password'] ?? '')
                ),
                'type' => 'text'
            ],
            'api_key' => [
                'key' => $defaults['key_name'] ?? 'X-API-KEY',
                'value' => $defaults['key_value'] ?? '',
                'type' => 'text'
            ],
            default => []
        };
    }
}
