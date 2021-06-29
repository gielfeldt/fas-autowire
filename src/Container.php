<?php

namespace Fas\Autowire;

use Fas\Autowire\Exception\InvalidDefinitionException;
use Psr\Container\ContainerInterface;
use ReflectionClass;

class Container implements ContainerInterface
{
    private array $resolved = [];
    private Autowire $autowire;

    public function __construct(?Autowire $autowire = null)
    {
        $this->autowire = $autowire ?? new Autowire($this);
        $this->definitions[ContainerInterface::class] = function () {
            return $this;
        };
    }

    public function has(string $id): bool
    {
        return isset($this->definitions[$id]) || (class_exists($id) && (new ReflectionClass($id))->isInstantiable());
    }

    public function get(string $id)
    {
        $definition = $this->definitions[$id] ?? $id;
        if ($this->resolved[$id]) {
            return $this->resolved[$id];
        }
        if (is_string($definition)) {
            return $this->resolved[$id] = $this->autowire->new($definition);
        }
        if (is_callable($definition)) {
            return $this->resolved[$id] = $this->autowire->call($definition);
        }
        throw new InvalidDefinitionException($id, var_export($definition, true));
    }

    public function set(string $id, $className)
    {
        $this->definitions[$id] = $className;
    }
}
