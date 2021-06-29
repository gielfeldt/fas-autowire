<?php

declare(strict_types=1);

namespace Fas\Autowire\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;
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
        $type = $p->hasType() ? $p->getType()->getName() : null;
        return "$functionName($type \$$name)";
    }

    public static function throw($id, $argument)
    {
        throw new self($id, $argument);
    }
}
