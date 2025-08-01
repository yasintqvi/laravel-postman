<?php

namespace YasinTgh\LaravelPostman\Exceptions;

use RuntimeException;
use Throwable;
use Illuminate\Routing\Route;

class RouteProcessingException extends RuntimeException
{
    public function __construct(
        string $message = "Route processing failed",
        int $code = 0,
        ?Throwable $previous = null,
        protected ?Route $route = null,
        protected ?string $failureType = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    public function context(): array
    {
        return [
            'route_uri' => $this->route?->uri(),
            'route_methods' => $this->route?->methods(),
            'route_action' => $this->route?->getActionName(),
            'failure_type' => $this->failureType,
        ];
    }

    public static function forReflectionFailure(
        Route $route,
        Throwable $previous,
        string $failureType
    ): self {
        return new static(
            message: sprintf(
                'Failed to process route "%s": %s',
                $route->uri(),
                $previous->getMessage()
            ),
            previous: $previous,
            route: $route,
            failureType: $failureType
        );
    }
}
