<?php

namespace YasinTgh\LaravelPostman\Services;

use Closure;
use Illuminate\Container\Container;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Throwable;
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

            $formRequestType = null;
            foreach ($reflector->getParameters() as $parameter) {
                if ($formRequestType = $this->resolveFormRequestClass($parameter)) {
                    break;
                }
            }

            $formRequest = $formRequestType ? $this->makeFormRequest($formRequestType, $route) : null;

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
        } catch (Throwable $e) {
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
            if (Str::is(trim($pattern, "/"), $route->uri())) {
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

        $includeControllers = $this->config['routes']['include']['controllers'] ?? [];
        if (!empty($includeControllers) && !in_array($controller, $includeControllers, true)) {
            return false;
        }

        if ($controller && in_array($controller, $this->config['routes']['exclude']['controllers'] ?? [], true)) {
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

    /**
     * Returns the FormRequest class a parameter is typed with, if any.
     * Handles named, union, intersection and DNF types.
     */
    private function resolveFormRequestClass(ReflectionParameter $p): ?string
    {
        $type = $p->getType();
        if (!$type) return null;

        $pending = [$type];
        while ($t = array_shift($pending)) {
            if ($t instanceof ReflectionNamedType) {
                if (!$t->isBuiltin() && is_subclass_of($t->getName(), FormRequest::class)) {
                    return $t->getName();
                }
            } elseif ($t instanceof ReflectionUnionType || $t instanceof ReflectionIntersectionType) {
                array_push($pending, ...$t->getTypes());
            }
        }

        return null;
    }

    /**
     * Builds a FormRequest that can run rules() outside an HTTP request:
     * it has the container, a resolvable (empty) route and no user, but is
     * NOT resolved through the container, which would trigger validation.
     */
    private function makeFormRequest(string $class, Route $route): FormRequest
    {
        /** @var FormRequest $request */
        $request = new $class();

        $request->setContainer(Container::getInstance());

        $boundRoute = clone $route;
        $boundRoute->parameters = [];
        $request->setRouteResolver(fn() => $boundRoute);
        $request->setUserResolver(fn() => null);

        return $request;
    }
}
