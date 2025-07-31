<?php

namespace YasinTgh\LaravelPostman\Services;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionParameter;
use YasinTgh\LaravelPostman\Contracts\RouteAnalyzerInterface;
use YasinTgh\LaravelPostman\DataTransferObjects\RouteInfoDto;

class RouteAnalyzer implements RouteAnalyzerInterface
{
    public function __construct(
        protected Router $router,
        protected array $config
    ) {}

    public function analyze(): array
    {
        return array_map(
            [$this, 'parseRoute'],
            $this->getFilteredRoutes()
        );
    }

    protected function getFilteredRoutes(): array
    {
        return array_filter($this->router->getRoutes()->getRoutes(), function (Route $route) {
            if ($this->shouldIncludeRoute($route)) {
                return str_starts_with($route->uri(), $this->config['routes']['prefix']);
            }
            return false;
        });
    }

    protected function parseRoute(Route $route): RouteInfoDto
    {
        $controllerMethod = new ReflectionMethod($route->getControllerClass(), $route->getActionMethod());

        $formRequestType = collect($controllerMethod->getParameters())
            ->first(fn($parameter) => $this->isFormRequest($parameter))
            ?->getType()
            ?->getName();

        $formRequest = $formRequestType ? new $formRequestType() : null;

        return new RouteInfoDto(
            $route->uri(),
            $route->methods(),
            $route->getControllerClass(),
            $route->getActionMethod(),
            $formRequest,
            $route->gatherMiddleware(),
            $this->isProtectedRoute($route),

        );
    }

    protected function shouldIncludeRoute(Route $route): bool
    {
        $prefix = $this->config['routes']['prefix'] ?? 'api';
        if ($prefix && !str_starts_with($route->uri(), $prefix)) {
            return false;
        }

        if (!empty($this->config['routes']['include']['patterns'])) {
            $matched = false;
            foreach ($this->config['routes']['include']['patterns'] as $pattern) {
                if (Str::is($pattern, $route->uri())) {
                    $matched = true;
                    break;
                }
            }
            if (!$matched) return false;
        }

        foreach ($this->config['routes']['exclude']['patterns'] as $pattern) {
            if (Str::is($pattern, $route->uri())) {
                return false;
            }
        }

        if (!empty($this->config['routes']['include']['middleware'])) {
            $routeMiddleware = $route->gatherMiddleware();
            if (empty(array_intersect($this->config['routes']['include']['middleware'], $routeMiddleware))) {
                return false;
            }
        }

        foreach ($this->config['routes']['exclude']['middleware'] as $middleware) {
            if (in_array($middleware, $route->gatherMiddleware())) {
                return false;
            }
        }

        $controller = $route->getControllerClass();
        if ($controller && in_array($controller, $this->config['routes']['exclude']['controllers'])) {
            return false;
        }

        return true;
    }

    protected function isProtectedRoute(Route $route): bool
    {
        $authMiddleware = $this->config['auth']['protected_middleware'] ?? ['auth'];

        return !empty(array_intersect(
            $authMiddleware,
            $route->gatherMiddleware()
        ));
    }

    private function isFormRequest(ReflectionParameter $parameter): bool
    {
        $parameterType = $parameter->getType();
        return $parameterType && !$parameterType->isBuiltin() && is_subclass_of($parameterType->getName(), FormRequest::class);
    }
}
