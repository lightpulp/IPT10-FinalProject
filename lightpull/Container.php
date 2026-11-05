<?php

declare(strict_types=1);

namespace Lightpull;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use RuntimeException;

/** Factories by key, cached singletons, and constructor autowiring by type hint. */
final class Container
{
    /** @var array<string, array{0: callable, 1: bool}> */
    private array $bindings = [];
    private array $instances = [];

    public function bind(string $key, callable $factory): void
    {
        $this->bindings[$key] = [$factory, false];
        unset($this->instances[$key]);
    }

    public function singleton(string $key, callable $factory): void
    {
        $this->bindings[$key] = [$factory, true];
        unset($this->instances[$key]);
    }

    public function get(string $key): mixed
    {
        if (array_key_exists($key, $this->instances)) {
            return $this->instances[$key];
        }
        if (!isset($this->bindings[$key])) {
            return $this->build($key);
        }

        [$factory, $shared] = $this->bindings[$key];
        $object = $factory($this);
        if ($shared) {
            $this->instances[$key] = $object;
        }
        return $object;
    }

    private function build(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Container cannot resolve [{$class}]: no binding and no such class.");
        }
        $reflection = new ReflectionClass($class);
        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Container cannot instantiate [{$class}].");
        }
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            return new $class();
        }

        $arguments = array_map(
            fn (ReflectionParameter $parameter) => $this->resolveParameter($parameter, $class),
            $constructor->getParameters(),
        );
        return $reflection->newInstanceArgs($arguments);
    }

    private function resolveParameter(ReflectionParameter $parameter, string $class): mixed
    {
        $type = $parameter->getType();
        if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
            return $this->get($type->getName());
        }
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }
        throw new RuntimeException(sprintf(
            'Container cannot resolve parameter $%s of [%s]: it is a scalar with no default. Bind the class explicitly.',
            $parameter->getName(),
            $class,
        ));
    }
}
