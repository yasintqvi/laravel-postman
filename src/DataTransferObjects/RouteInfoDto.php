<?php

namespace YasinTgh\LaravelPostman\DataTransferObjects;

readonly class RouteInfoDto
{
    public function __construct(
        public string $uri,
        public array $methods,
        public ?string $controller = null,
        public ?string $action = null,
        public array $middleware = [],
        public bool $isProtected
    ) {}

    public function getName(): string
    {
        return $this->methods[0] . ' ' . $this->uri;
    }
}
