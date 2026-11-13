<?php

namespace YasinTgh\LaravelPostman\Services;

use YasinTgh\LaravelPostman\DataTransferObjects\RouteInfoDto;

class NameGenerator
{
    public function __construct(
        protected array $config
    ) {}

    public function generate(RouteInfoDto $route): string
    {
        $default = '[{method}] {uri}';
        $template = $this->config['structure']['naming_format'] ?? $default;

        // Closures have no controller or action, so "{controller}@{action}"
        // would render as "@Closure". Name them by method and URI instead.
        if (!$route->controller && preg_match('/\{(controller|action)\}/', $template)) {
            $template = $default;
        }

        // Laravel reports an invokable controller's action as its class name.
        $action = $route->action === $route->controller ? '__invoke' : $route->action;

        return str_replace(
            ['{method}', '{uri}', '{controller}', '{action}'],
            [$route->methods[0], $route->uri, $route->controller, $action],
            $template
        );
    }
}
