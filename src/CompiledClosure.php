<?php

declare(strict_types=1);

namespace Fas\Autowire;

use Closure;
use Fas\Exportable\ExportableInterface;
use Fas\Exportable\Exporter;
use Psr\Container\ContainerInterface;

class CompiledClosure implements ExportableInterface
{
    private string $code;
    private ?Closure $closure = null;

    public function __construct(string $code)
    {
        $this->code = $code;
    }

    public function __invoke(ContainerInterface $container, array $args = [])
    {
        if ($this->closure === null) {
            eval("\$this->closure = $this->code;");
        }
        return ($this->closure)($container, $args);
    }

    public function exportable(Exporter $exporter, $level = 0): string
    {
        return $this->code;
    }

    public function __toString()
    {
        return $this->code;
    }
}
