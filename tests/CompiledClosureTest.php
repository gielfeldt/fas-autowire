<?php

namespace Fas\Autowire\Tests;

use Fas\Autowire\Autowire;
use Fas\Exportable\Exporter;
use PHPUnit\Framework\TestCase;

class CompiledClosureTest extends TestCase
{
    public function testCompiledClosureIsCallable()
    {
        $autowire = new Autowire();
        $closure = static function () {
            return 'test';
        };
        $closure = $autowire->compileCall($closure);

        $this->assertEquals('test', $closure($autowire->getContainer()));
    }

    public function testCompiledClosureReturnsCodeAsString()
    {
        $autowire = new Autowire();
        $closure = static function () {
            return 'test';
        };
        $compiledClosure = $autowire->compileCall($closure);

        $closure = null;
        eval("\$closure = $compiledClosure;");
        $this->assertEquals('test', $closure($autowire->getContainer()));
    }

    public function testCompiledClosureExportsCodeAsString()
    {
        $autowire = new Autowire();
        $closure = static function () {
            return 'test';
        };
        $compiledClosure = $autowire->compileCall($closure);

        $exporter = new Exporter();
        $code = $exporter->export($compiledClosure);
        $closure = null;
        eval("\$closure = $code;");
        $this->assertEquals('test', $closure($autowire->getContainer()));
    }
}
