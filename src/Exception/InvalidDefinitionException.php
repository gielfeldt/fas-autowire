<?php

declare(strict_types=1);

namespace Fas\Autowire\Exception;

use Exception;
use Psr\Container\ContainerExceptionInterface;
use Throwable;

class InvalidDefinitionException extends Exception implements ContainerExceptionInterface
{
    public function __construct(string $id, string $definition, ?Throwable $previous = null)
    {
        parent::__construct("Cannot resolve '$id'. Invalid definition: '$definition");
    }
}
