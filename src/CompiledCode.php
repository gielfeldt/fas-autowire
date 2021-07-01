<?php

declare(strict_types=1);

namespace Fas\Autowire;

use Fas\Exportable\ExportableInterface;
use Fas\Exportable\Exporter;

class CompiledCode implements ExportableInterface
{
    protected string $code;

    public function __construct(string $code)
    {
        $this->code = $code;
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
