<?php

declare(strict_types=1);

namespace Fas\Autowire\Exception;

use Exception;
use Psr\Container\NotFoundExceptionInterface;
use Throwable;

class NotFoundException extends Exception implements NotFoundExceptionInterface
{
    public function __construct(string $id, ?Throwable $previous = null)
    {
        $message = "'$id' not found";
        parent::__construct($message, 0, $previous);
    }
}
