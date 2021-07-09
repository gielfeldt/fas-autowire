<?php

declare(strict_types=1);

namespace Fas\Autowire;

use Closure;
use Psr\Container\ContainerInterface;

class CompiledClosure extends CompiledCode
{
    protected ?Closure $closure = null;
    protected string $functionName = '';
    protected string $visibility = '';
    protected string $static = 'static';

    public function __invoke(ContainerInterface $container, array $args = [])
    {
        if ($this->closure === null) {
            eval("\$this->closure = $this;");
        }
        return ($this->closure)($container, $args);
    }

    public function setFunctionName(string $functionName)
    {
        $this->functionName = $functionName;
    }

    public function setStatic(bool $static)
    {
        $this->static = $static ? 'static' : '';
    }

    public function setVisibilty(string $visibility)
    {
        $this->visibility = $visibility;
    }

    public function __toString()
    {
        return "$this->visibility $this->static function $this->functionName (\\" . ContainerInterface::class . ' $container, array $args = []) { ' . $this->code . ' }';
    }
}
