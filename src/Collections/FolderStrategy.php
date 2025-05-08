<?php

namespace YasinTgh\LaravelPostman\Collections;

use YasinTgh\LaravelPostman\DataTransferObjects\RouteInfoDto;

class FolderStrategy
{
    public function __construct(
        protected string $strategy,
        protected array $config
    ) {}

    public function organize(array $routes): array
    {
        return match ($this->strategy) {
            'prefix' => $this->groupByPrefix($routes),
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
            'name' => $route->getName(),
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
