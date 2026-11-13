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
        // Laravel params may be optional ({id?}) or bind a field ({post:slug});
        // Postman path variables only take the bare name (:id, :post).
        $paramPattern = '/\{([^}:?]+)(?::[^}?]+)?\??\}/';

        preg_match_all($paramPattern, $route->uri, $paramMatches);
        $pathParams = array_values(array_unique($paramMatches[1]));
        $hasParams  = !empty($pathParams);

        $rawUri = preg_replace($paramPattern, ':$1', $route->uri);

        $pathSegments = array_values(array_filter(
            explode('/', $rawUri),
            fn(string $segment) => $segment !== ''
        ));

        $formatted = [
            'name'    => $this->name_generator->generate($route),
            'request' => [
                'method' => $route->methods[0],
                'header' => $this->buildHeaders($route),
                'url'    => [
                    'raw'  => '{{base_url}}/' . $rawUri,
                    'host' => ['{{base_url}}'],
                    'path' => $pathSegments,
                ],
            ],
        ];

        if ($hasParams) {
            $formatted['request']['url']['variable'] = array_map(
                fn(string $param) => [
                    'key'   => $param,
                    'value' => $this->requestConfig[$param] ?? '',
                ],
                $pathParams
            );
        }

        if ($route->formRequest) {
            $formatted['request']['body'] = $this->bodyGenerator->generateFromRequest(
                $route->formRequest,
                $this->config,
                $route->methods[0],
            );
        }

        // Content-Type only describes a raw body. Without a body it is noise,
        // and for form-data Postman must set it itself (with the multipart
        // boundary); a fixed "application/json" would break the request.
        if (($formatted['request']['body']['mode'] ?? null) !== 'raw') {
            $formatted['request']['header'] = array_values(array_filter(
                $formatted['request']['header'],
                fn(array $header) => strcasecmp($header['key'], 'Content-Type') !== 0
            ));
        }

        if ($this->isProtectedRoute($route)) {
            $formatted['request']['auth'] = $this->buildRouteAuth();
        }

        return $formatted;
    }

    protected function buildHeaders(RouteInfoDto $route): array
    {
        $headers = $this->buildDefaultHeaders();

        if (
            $this->isProtectedRoute($route)
            && $this->isApiKeyAuth()
            && ($this->config['auth']['location'] ?? 'header') === 'header'
        ) {
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
                'value' => (string) $value,
            ];
        }
        return $headers;
    }

    protected function isProtectedRoute(RouteInfoDto $route): bool
    {
        // Without auth enabled the collection defines no auth variables,
        // so request-level auth would reference undefined {{...}} values.
        if (!($this->config['auth']['enabled'] ?? false)) {
            return false;
        }

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
        $maxDepth = max(0, (int) ($this->config['structure']['folders']['max_depth'] ?? 2));
        $result = [];

        foreach ($routes as $route) {
            $folders = array_slice($this->staticSegments($route->uri), 0, $maxDepth);

            $current = &$result;

            foreach ($folders as $segment) {
                $index = $this->findFolderIndex($current, $segment);

                if ($index === null) {
                    $current[] = ['name' => $segment, 'item' => []];
                    $index = array_key_last($current);
                }

                $current = &$current[$index]['item'];
            }

            // Routes deeper than max_depth land in the deepest allowed folder;
            // routes with no static segment (e.g. "api" or "api/{locale}") land at the root.
            $current[] = $this->formatRoute($route);

            unset($current);
        }

        return $result;
    }

    /**
     * URI segments after the configured prefix, without route parameters.
     */
    protected function staticSegments(string $uri): array
    {
        $uri = trim($uri, '/');
        $prefix = trim((string) ($this->config['routes']['prefix'] ?? ''), '/');

        if ($prefix !== '' && ($uri === $prefix || str_starts_with($uri, $prefix . '/'))) {
            $uri = substr($uri, strlen($prefix));
        }

        return array_values(array_filter(
            explode('/', $uri),
            fn(string $segment) => $segment !== '' && !preg_match('/^\{.*\}$/', $segment)
        ));
    }

    protected function findFolderIndex(array $items, string $name): int|string|null
    {
        foreach ($items as $index => $item) {
            if (isset($item['item']) && !isset($item['request']) && ($item['name'] ?? null) === $name) {
                return $index;
            }
        }

        return null;
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
