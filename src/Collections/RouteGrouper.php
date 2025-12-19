<?php

namespace YasinTgh\LaravelPostman\Collections;

use YasinTgh\LaravelPostman\DataTransferObjects\RouteInfoDto;
use YasinTgh\LaravelPostman\Services\NameGenerator;
use YasinTgh\LaravelPostman\Services\RequestBodyGenerator;

class RouteGrouper
{
    public function __construct(
        protected string               $strategy,
        protected array                $config,
        protected NameGenerator        $name_generator,
        protected RequestBodyGenerator $bodyGenerator,
        public                         $requestConfig,
    ) {}

    public function organize(array $routes): array
    {
        return match ($this->strategy) {
            'prefix' => $this->groupByPrefix($routes),
            'nested_path' => $this->groupByNestedPath($routes),
            'controller' => $this->groupByController($routes),
            default => $this->groupByPrefix($routes)
        };
    }

    protected function groupByPrefix(array $routes): array
    {
        $groups = [];

        foreach ($routes as $route) {
            $prefix = explode('/', $route->uri)[1] ?? 'other';
            $groups[$prefix][] = $this->formatRoute($route);
        }

        return array_map(
            fn($items, $name) => ['name' => $name, 'item' => $items],
            $groups,
            array_keys($groups)
        );
    }

    protected function formatRoute(RouteInfoDto $route): array
    {
        $variable = false;

        if (str_contains($route->uri, '{') | str_contains($route->uri, '}')) {
            $variable = true;
        }

        $newUri = str_replace(['{', '}'], [':', ''], $route->uri);

        $formatted = [
            'name' => $this->name_generator->generate($route),
            'request' => [
                'method' => $route->methods[0],
                'header' => $this->buildHeaders($route),
                'url' => [
                    'raw' => '{{base_url}}/' . $newUri,
                    'host' => ['{{base_url}}'],
                    'path' => explode('/', $newUri),
                ]
            ]
        ];

        if ($variable) {
            $formatted['request']['url']['variable'] = [];
            $matches = preg_match('/\{([^}]+)\}/', $route->uri);
            preg_match_all('/\{([^}]+)\}/', $route->uri, $matches);

            foreach ($matches[1] as $param) {
                if (isset($this->requestConfig[$param])) {
                    $formatted['request']['url']['variable'][] = [
                        'key' => $param,
                        'value' => $this->requestConfig[$param]
                    ];
                }
            }
        }

        if ($route->formRequest) {
            $formatted['request']['body'] = $this->bodyGenerator->generateFromRequest(
                $route->formRequest,
                $this->config,
                $route->methods[0],
            );
        }

        if ($this->isProtectedRoute($route)) {
            $formatted['request']['auth'] = $this->buildRouteAuth();
        }

        return $formatted;
    }

    protected function buildHeaders(RouteInfoDto $route): array
    {
        $headers = $this->buildDefaultHeaders();

        if ($this->isProtectedRoute($route) && $this->isApiKeyAuth()) {
            $headers[] = $this->buildApiKeyHeader();
        }

        return $headers;
    }

    protected function buildDefaultHeaders(): array
    {
        $headers = [];
        foreach ($this->config['headers'] ?? [] as $key => $value) {
            $headers[] = [
                'key' => $key,
                'value' => $value,
                'type' => 'text'
            ];
        }
        return $headers;
    }

    protected function isProtectedRoute(RouteInfoDto $route): bool
    {
        $authMiddleware = $this->config['auth']['protected_middleware'] ?? ['auth'];
        return !empty(array_intersect($authMiddleware, $route->middleware));
    }

    protected function isApiKeyAuth(): bool
    {
        return ($this->config['auth']['type'] ?? null) === 'api_key';
    }

    protected function buildApiKeyHeader(): array
    {
        return [
            'key' => $this->config['auth']['default']['key_name'] ?? 'X-API-KEY',
            'value' => '{{api_key}}',
            'type' => 'text'
        ];
    }

    protected function buildRouteAuth(): array
    {
        $authConfig = $this->config['auth'] ?? [];

        switch ($authConfig['type'] ?? 'bearer') {
            case 'bearer':
                return [
                    'type' => 'bearer',
                    'bearer' => [
                        ['key' => 'token', 'value' => '{{auth_token}}']
                    ]
                ];

            case 'basic':
                return [
                    'type' => 'basic',
                    'basic' => [
                        ['key' => 'username', 'value' => '{{auth_username}}'],
                        ['key' => 'password', 'value' => '{{auth_password}}']
                    ]
                ];

            case 'api_key':
                return [
                    'type' => 'apikey',
                    'apikey' => [
                        ['key' => 'key', 'value' => $authConfig['default']['key_name'] ?? 'X-API-KEY'],
                        ['key' => 'value', 'value' => '{{api_key}}'],
                        ['key' => 'in', 'value' => $authConfig['location'] ?? 'header']
                    ]
                ];

            default:
                return [];
        }
    }

    protected function groupByNestedPath(array $routes): array
    {
        $maxDepth = $this->config['structure']['folders']['max_depth'] ?? 2;
        $result = [];

        foreach ($routes as $route) {
            $uriWithoutPrefix = trim(str_replace($this->config['routes']['prefix'], '', $route->uri), "/");

            $uriWithoutSegments = preg_replace('/\{.*?\}/', '', $uriWithoutPrefix);

            $uriWithoutSegments = trim(preg_replace('/\/+/', '/', $uriWithoutSegments), "/");

            if (empty($uriWithoutSegments)) {
                $segments = [];
            } else {
                $segments = explode('/', $uriWithoutSegments);
            }

            $current = &$result;

            foreach ($segments as $i => $segment) {
                if ($i < $maxDepth) {
                    $found = false;
                    foreach ($current as &$item) {
                        if (isset($item['name']) && $item['name'] === $segment) {
                            $current = &$item['item'];
                            $found = true;
                            break;
                        }
                    }

                    if (!$found) {
                        $newItem = [
                            'name' => $segment,
                            'item' => []
                        ];
                        $current[] = $newItem;
                        $current = &$current[count($current) - 1]['item'];
                    }

                    if ($i === count($segments) - 1) {
                        $current[] = $this->formatRoute($route);
                    }
                }
            }
        }

        return $result;
    }

    protected function groupByController(array $routes): array
    {
        $groups = [];

        foreach ($routes as $route) {

            $controllerName = $this->extractControllerName($route->controller);

            $groups[$controllerName][] = $this->formatRoute($route);
        }

        return array_map(
            fn($items, $name) => ['name' => $name, 'item' => $items],
            $groups,
            array_keys($groups)
        );
    }

    protected function extractControllerName(?string $controllerClass): string
    {
        if (!$controllerClass) {
            return 'Undefined';
        }

        $baseName = class_basename($controllerClass);
        return str_replace('Controller', '', $baseName);
    }
}
