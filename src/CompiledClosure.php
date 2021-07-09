<?php

declare(strict_types=1);

namespace Fas\Autowire;

use Closure;
use Psr\Container\ContainerInterface;

class CompiledClosure extends CompiledCode
{
    protected ?Closure $closure = null;

    public function __invoke(ContainerInterface $container, array $args = [])
    {
        if ($this->closure === null) {
            eval("\$this->closure = $this->code;");
        }
        return ($this->closure)($container, $args);
    }
}
