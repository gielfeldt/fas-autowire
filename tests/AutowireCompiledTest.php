<?php

namespace Fas\Autowire\Tests;

use Fas\Autowire\Container;
use Fas\Autowire\Exception\DefaultValueException;
use Fas\Autowire\Exception\InvalidDefinitionException;
use InvalidArgumentException;
use Psr\Container\ContainerInterface;

class AutowireCompiledTest extends AutowireTest
{

    protected function compile($callback, array $args = [])
    {
        return $this->autowire->compileCall($callback, $args);
    }

    protected function call($callback, array $args = [])
    {
        $closure = $this->autowire->compileCall($callback);
        return $closure($this->autowire->getContainer(), $args);
    }

    protected function new($className, array $args = [])
    {
        $closure = $this->autowire->compileNew($className);
        return $closure($this->autowire->getContainer(), $args);
    }

    public function testWillFailOnCompilingObjectInstance()
    {
        $test = new TestImplementation();

        $this->expectException(InvalidArgumentException::class);
        $this->call([$test, 'implementation'], [
            'args' => ['c','b','a']
        ]);
    }

    public function testCanCompileDefaultValues()
    {
        $closure = $this->compile(static function (TestImplementation $test, array &$args = ['q', 'r', 's']) {
            $r = $test->implementation(join(',', $args));
            return $r;
        }, [
            'args' => ['z', 'x', 'v']
        ]);
        $result = $closure($this->autowire->getContainer());
        $this->assertEquals("Z,X,V", $result);
    }

    public function testCanOverrideCompiledDefaultValues()
    {
        $closure = $this->compile(static function (TestImplementation $test, array &$args = ['q', 'r', 's']) {
            $r = $test->implementation(join(',', $args));
            return $r;
        }, [
            'args' => ['z', 'x', 'v']
        ]);
        $result = $closure($this->autowire->getContainer(), [
            'args' => ['a', 's', 'd']
        ]);
        $this->assertEquals("A,S,D", $result);
    }

    public function testMissingOptionalWithoutDefaultValueWillNotFailIfLast()
    {
        // Compiled new does not support this in php < 8
        if (version_compare(PHP_VERSION, '8.0', '<')) {
            $this->expectException(DefaultValueException::class);
        }
        parent::testMissingOptionalWithoutDefaultValueWillNotFailIfLast();
    }

    public function testWillResolvePostInjectedContainerValue()
    {
        $code = $this->compile(function (TestInterface $test) {
            return $test->implementation('works');
        });
        $container = $this->autowire->getContainer();
        assert($container instanceof Container);
        $container->set(TestInterface::class, TestImplementation::class);

        $result = $code($this->autowire->getContainer());
        $this->assertEquals('WORKS', $result);
    }
}
