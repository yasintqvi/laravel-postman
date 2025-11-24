<?php

namespace YasinTgh\LaravelPostman\DataTransferObjects;

use Illuminate\Foundation\Http\FormRequest;

class RouteInfoDto
{
    public function __construct(
        readonly public string $uri,
        readonly public array $methods,
        readonly public bool $isProtected,
        readonly public ?string $controller = null,
        readonly public ?string $action = null,
        readonly public ?FormRequest $formRequest = null,
        readonly public array $middleware = [],
    ) {}

    public function getName(): string
    {
        return $this->methods[0] . ' ' . $this->uri;
    }
}
