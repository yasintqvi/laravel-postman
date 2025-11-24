<?php

namespace YasinTgh\LaravelPostman\Services;

use Closure;
use Exception;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionUnionType;
use YasinTgh\LaravelPostman\Contracts\RouteAnalyzerInterface;
use YasinTgh\LaravelPostman\DataTransferObjects\RouteInfoDto;
use YasinTgh\LaravelPostman\Exceptions\RouteProcessingException;
use YasinTgh\LaravelPostman\Exceptions\UnsupportedRouteException;

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
        try {
            $reflector = $this->getRouteReflector($route);

            $formRequestType = collect($reflector->getParameters())
                ->first(fn($parameter) => $this->isFormRequest($parameter))
                ?->getType()
                ?->getName();


            $formRequest = $formRequestType ? new $formRequestType() : null;

            return new RouteInfoDto(
                uri: $route->uri(),
                methods: $route->methods(),
                controller: $route->getControllerClass(),
                action: $route->getActionMethod(),
                formRequest: $formRequest,
                middleware: $route->gatherMiddleware(),
                isProtected: $this->isProtectedRoute($route)
            );
        } catch (UnsupportedRouteException $e) {
            throw $e;
        } catch (Exception $e) {
            throw RouteProcessingException::forReflectionFailure(
                route: $route,
                previous: $e,
                failureType: 'reflection_failure'
            );
        }
    }

    protected function getRouteReflector(Route $route): ReflectionFunctionAbstract
    {
        $controller = $route->getControllerClass();

        $method = $route->getActionMethod();

        if ($route->getAction('uses') instanceof Closure) {
            return new ReflectionFunction($route->getAction('uses'));
        }

        if (!class_exists($controller)) {
            throw UnsupportedRouteException::forMissingController(
                uri: $route->uri(),
                controller: $controller
            );
        }

        $targetMethod = $method === $controller ? '__invoke' : $method;
        if (!method_exists($controller, $targetMethod)) {
            throw UnsupportedRouteException::forMissingHandler(
                uri: $route->uri(),
                controller: $controller,
                method: $targetMethod
            );
        }

        return new ReflectionMethod($controller, $targetMethod);
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

    private function isFormRequest(ReflectionParameter $p): bool
    {
        $type = $p->getType();
        if (!$type) return false;

        $types = $type instanceof ReflectionUnionType
            ? $type->getTypes()
            : [$type];

        foreach ($types as $t) {
            if (!$t->isBuiltin() && is_subclass_of($t->getName(), FormRequest::class)) {
                return true;
            }
        }

        return false;
    }
}
