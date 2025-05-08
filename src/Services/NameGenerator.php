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
        $strategy = $this->config['structure']['name']['strategy'];
        $template = $this->config['structure']['name']['available_strategies'][$strategy];

        return str_replace(
            ['{method}', '{uri}', '{controller}', '{action}'],
            [$route->methods[0], $route->uri, $route->controller, $route->action],
            $template
        );
    }
}
