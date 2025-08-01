<?php

namespace YasinTgh\LaravelPostman\Exceptions;

use RuntimeException;
use Throwable;

class UnsupportedRouteException extends RuntimeException
{
    public function __construct(
        string $message = "Unsupported route configuration",
        int $code = 0,
        ?Throwable $previous = null,
        protected ?string $routeUri = null,
        protected ?string $controller = null,
        protected ?string $method = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function context(): array
    {
        return [
            'route' => $this->routeUri,
            'controller' => $this->controller,
            'method' => $this->method,
        ];
    }

    public static function forMissingHandler(
        string $uri,
        ?string $controller,
        ?string $method
    ): self {
        return new static(
            message: sprintf(
                'Route "%s" has no valid handler. Controller: %s, Method: %s',
                $uri,
                $controller ?? 'None',
                $method ?? 'None'
            ),
            routeUri: $uri,
            controller: $controller,
            method: $method
        );
    }

    public static function forMissingController(string $uri, string $controller): self
    {
        return new static(
            message: sprintf('Controller class "%s" does not exist for route "%s"', $controller, $uri),
            routeUri: $uri,
            controller: $controller
        );
    }
}
