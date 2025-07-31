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
        $template = $this->config['structure']['naming_format'] ?? '[{method}] {uri}';

        return str_replace(
            ['{method}', '{uri}', '{controller}', '{action}'],
            [$route->methods[0], $route->uri, $route->controller, $route->action],
            $template
        );
    }
}
