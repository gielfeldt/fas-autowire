<?php

declare(strict_types=1);

namespace Fas\Autowire\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use ReflectionUnionType;
use ReflectionNamedType;
use ReflectionParameter;
use Throwable;

class DefaultValueException extends Exception implements ContainerExceptionInterface
{
    public function __construct(?string $id, string $argument, ?Throwable $previous = null)
    {
        parent::__construct("[$id] Argument: $argument has no default value while resolving");
    }

    public static function argument(ReflectionParameter $p)
    {
        $r = $p->getDeclaringFunction();
        $functionName = $r->getName();
        $className = $r->getClosureScopeClass();
        $className = $className ? $className->getName() : null;
        $functionName = $className ? "$className::$functionName" : $functionName;

        $name = $p->getName();
        $type = self::getTypeString($p);
        return "$functionName($type \$$name)";
    }
    private static function getTypeString(ReflectionParameter $p)
    {
        $types = $p->hasType() ? $p->getType() : [];
        $types = $types && $types instanceof ReflectionUnionType ? $types->getTypes() : [$types];
        $types = array_filter($types, function ($type) {
            return $type instanceof ReflectionNamedType;
        });
        $types = array_map(function ($type) {
            return $type->getName();
        }, $types);
        return implode('|', $types);
}

    public static function throw($id, $argument)
    {
        throw new self($id, $argument);
    }
}
