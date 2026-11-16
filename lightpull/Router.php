<?php

declare(strict_types=1);

namespace Lightpull;

use Closure;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;

final class Router
{
    /** @var array<string, array<string, Route>> method => path => Route */
    private array $routes = [];

    /** Middleware of the group() calls currently open, innermost last. */
    private array $groupMiddleware = [];

    public function get(string $path, array|callable $handler): Route
    {
        return $this->add('GET', $path, $handler);
    }

    public function post(string $path, array|callable $handler): Route
    {
        return $this->add('POST', $path, $handler);
    }

    /**
     * Every route registered inside $routes gets $middleware, before any it adds itself.
     *
     *     $router->group([Authenticate::class], function (Router $router) { ... });
     */
    public function group(array $middleware, callable $routes): void
    {
        $this->groupMiddleware[] = $middleware;
        try {
            $routes($this);
        } finally {
            array_pop($this->groupMiddleware);
        }
    }

    public function dispatch(Request $request, Container $container): Response
    {
        foreach ($this->routes[$request->method()] ?? [] as $path => $route) {
            if (preg_match($this->pattern($path), $request->path(), $matches)) {
                $params = array_map('rawurldecode', array_slice($matches, 1));
                return (new Pipeline($container))->run(
                    $route->middlewareList(),
                    $request,
                    fn () => $this->call($route->handler, $params, $container),
                );
            }
        }
        throw new HttpException(404);
    }

    private function add(string $method, string $path, array|callable $handler): Route
    {
        $handler = is_array($handler) ? $handler : Closure::fromCallable($handler);
        $route = new Route($handler, array_merge([], ...$this->groupMiddleware));
        $this->routes[$method]['/' . trim($path, '/')] = $route;
        return $route;
    }

    private function pattern(string $path): string
    {
        return '#^' . preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', $path) . '$#';
    }

    private function call(array|Closure $handler, array $params, Container $container): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $handler = [$container->get($class), $method];
            $reflection = new ReflectionMethod($handler[0], $method);
        } else {
            $reflection = new ReflectionFunction($handler);
        }

        $result = $handler(...$this->arguments($reflection, $params));
        return $result instanceof Response ? $result : Response::html((string) $result);
    }

    /** Route params are strings; a parameter typed int must be all digits or the route 404s. */
    private function arguments(ReflectionFunctionAbstract $function, array $params): array
    {
        $arguments = [];
        foreach ($function->getParameters() as $index => $parameter) {
            if (!array_key_exists($index, $params)) {
                break;
            }
            $value = $params[$index];
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && $type->getName() === 'int') {
                if (!ctype_digit($value) || strlen($value) > 9) {
                    throw new HttpException(404);
                }
                $value = (int) $value;
            }
            $arguments[] = $value;
        }
        return $arguments;
    }
}
