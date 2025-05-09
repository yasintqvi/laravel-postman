<?php

namespace YasinTgh\LaravelPostman\Collections;

use YasinTgh\LaravelPostman\DataTransferObjects\RouteInfoDto;
use YasinTgh\LaravelPostman\Services\NameGenerator;

class FolderStrategy
{
    public function __construct(
        protected string $strategy,
        protected array $config,
        protected NameGenerator $name_generator
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

    protected function groupByNestedPath(array $routes): array
    {
        $maxDepth = $this->config['structure']['folders']['max_depth'] ?? 2;
        $result = [];

        foreach ($routes as $route) {

            $uriWithoutPrefix = trim(str_replace($this->config['routes']['prefix'], '', $route->uri), "/");

            $uriWithoutSegments = trim(preg_replace('/\{.*?\}/', '', $uriWithoutPrefix), "/");

            $segments = explode('/', $uriWithoutSegments);

            $current = &$result;

            foreach ($segments as $i => $segment) {
                $isLast = ($i === count($segments) - 1);

                if ($isLast) {
                    $current[] = $this->formatRoute($route);
                } elseif ($i < $maxDepth) {
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

    protected function formatRoute(RouteInfoDto $route): array
    {
        return [
            'name' => $this->name_generator->generate($route),
            'request' => [
                'method' => $route->methods[0],
                'url' => [
                    'raw' => '{{base_url}}/' . $route->uri,
                    'host' => ['{{base_url}}'],
                    'path' => explode('/', $route->uri)
                ]
            ]
        ];
    }
}
