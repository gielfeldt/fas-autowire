<?php

namespace Fas\Autowire\Tests;

use DateTime;
use DateTimeZone;
use Fas\Autowire\Autowire;
use Fas\Autowire\Container;
use Fas\Autowire\Exception\CircularDependencyException;
use Fas\Autowire\Exception\DefaultValueException;
use Fas\Autowire\Exception\InvalidDefinitionException;
use Fas\Autowire\Exception\NotFoundException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

class AutowireTest extends TestCase
{
    protected Autowire $autowire;

    public function setup(): void
    {
        error_reporting(E_ALL);
        $container = new Container();
        $this->autowire = new Autowire($container);
        $container->set('test', TestImplementation::class);
        $container->set('test_factory', function (ContainerInterface $container) {
            return $container->get(TestImplementation::class);
        });
    }

    protected function call($callback, array $args = [])
    {
        return $this->autowire->call($callback, $args);
    }

    protected function new($className, array $args = [])
    {
        return $this->autowire->new($className, $args);
    }

    public function testCanCallClosureWithDefaultParameters()
    {
        $result = $this->call(static function ($name = 'abc') {
            return strtoupper($name);
        });

        $this->assertEquals("ABC", $result);
    }

    public function testCanCallClosureWithNamedParameter()
    {
        $result = $this->call(static function ($name = 'abc') {
            return strtoupper($name);
        }, ['name' => 'cba']);

        $this->assertEquals("CBA", $result);
    }

    public function testWillFailWhenClosureHasNoDefaultParameters()
    {
        $this->expectException(DefaultValueException::class);

        $this->call(static function ($name) {
            return strtoupper($name);
        });
    }

    public function testCanCallClassWithDefaultParameters()
    {
        $result = $this->call([TestImplementation::class, 'hasdefaultparameters']);

        $this->assertEquals("ABC", $result);
    }

    public function testCanCallClassWithNamedParameter()
    {
        $result = $this->call([TestImplementation::class, 'hasdefaultparameters'], ['name' => 'cba']);

        $this->assertEquals("CBA", $result);
    }

    public function testWillFailWhenMethodHasNoDefaultParameters()
    {
        $this->expectException(DefaultValueException::class);

        $this->call([TestImplementation::class, 'nodefaultparameters']);
    }

    public function testCanCallInvokableClassWithDefaultParameters()
    {
        $result = $this->call(TestImplementation::class);

        $this->assertEquals("ABC", $result);
    }

    public function testCanCallStaticClassMethodWithDefaultParameters()
    {
        $result = $this->call([TestImplementation::class, 'staticfunction']);

        $this->assertEquals("ABC", $result);
    }

    public function testCanHandleCircularReferences()
    {
        $this->expectException(CircularDependencyException::class);

        $this->new(Circular1::class);
    }

    public function testCanHandleCircularReferencesFromCall()
    {
        $this->expectException(CircularDependencyException::class);

        $this->call([Circular1::class, 'test']);
    }

    public function testCanHandleNonExistingClass()
    {
        $this->expectException(NotFoundException::class);

        $this->new('someclassthatdoesnotexist');
    }

    public function testCanCallWithVariadicParameters()
    {
        $result = $this->call(static function (TestImplementation $test, ...$args) {
            return $test->implementation(join(',', $args));
        }, [
            'args' => ['c', 'b', 'a']
        ]);

        $this->assertEquals("C,B,A", $result);
    }

    public function testCanCallUsingByReferenceParameters()
    {
        $args = ['c', 'b', 'a'];
        $result = $this->call(static function (TestImplementation $test, array &$args) {
            $r = $test->implementation(join(',', $args));
            $args = [1, 2, 3];
            return $r;
        }, [
            'args' => &$args,
        ]);

        $this->assertEquals("C,B,A", $result);
        $this->assertEquals([1, 2, 3], $args);
    }

    public function testCanCallUsingByReferenceParametersWithDefaultValue()
    {
        $args = ['c', 'b', 'a'];
        $result = $this->call(static function (TestImplementation $test, array &$args = ['c', 'b', 'a']) {
            $r = $test->implementation(join(',', $args));
            $args = [1, 2, 3];
            return $r;
        }, [
            'args' => &$args,
        ]);

        $this->assertEquals("C,B,A", $result);
        $this->assertEquals([1, 2, 3], $args);
    }

    public function testCanCallWithParametersByReferenceUsingDefaultValue()
    {
        $result = $this->call(static function (TestImplementation $test, array &$args = ['c', 'b', 'a']) {
            $r = $test->implementation(join(',', $args));
            return $r;
        });

        $this->assertEquals("C,B,A", $result);
    }

    public function testWillFailWithParametersByReferenceAndNoValues()
    {
        $this->expectException(DefaultValueException::class);
        $this->call(static function (TestImplementation $test, array &$args) {
            $r = $test->implementation(join(',', $args));
            return $r;
        });
    }

    public function testCanCallWithVariadicParametersByReference()
    {
        $args = ['c', 'b', 'a'];
        $result = $this->call(static function (TestImplementation $test, &...$args) {
            $r = $test->implementation(join(',', $args));
            foreach ($args as $i => &$arg) {
                $arg = $i + 1;
            }
            return $r;
        }, [
            'args' => &$args
        ]);

        $this->assertEquals("C,B,A", $result);
        $this->assertEquals([1, 2, 3], $args);
    }

    public function testCanUseStaticVariablesInClosure()
    {
        $outside = 'static';
        $result = $this->call(static function ($inside) use ($outside) {
            return strtoupper($outside . $inside);
        }, [
            'inside' => 'dynamic'
        ]);

        $this->assertEquals("STATICDYNAMIC", $result);
    }

    public function testWillFailOnNonInvokableClass()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->call(NonInvokableClass::class, [
            'args' => ['c', 'b', 'a']
        ]);
    }

    public function testWillFailOnCallingInvalidCallable()
    {
        $this->expectException(InvalidArgumentException::class);

        $this->call('somecallablethatdoesnotexist');
    }

    public function testReferenceTracker()
    {
        $referenceTracker = new TestReferenceTracker();
        $this->autowire->setReferenceTracker($referenceTracker);

        $this->new(TestImplementation2::class);

        $this->assertArrayHasKey(TestImplementation::class, $referenceTracker->references);
    }

    public function testCanResolveNonClassNameEntry()
    {
        $result = $this->call(['test', 'implementation'], ['name' => 'qwe']);
        $this->assertEquals('QWE', $result);
    }

    public function testCanResolveNonClassNameEntryFactory()
    {
        $result = $this->call(['test_factory', 'implementation'], ['name' => 'qwe']);
        $this->assertEquals('QWE', $result);
    }

    public function testMissingOptionalWithoutDefaultValueWillNotFailIfLast()
    {
        $datetime = $this->new(DateTime::class, ['time' => '2006-01-02T15:04:05+07:00']);
        $this->assertEquals('2006-01-02T15:04:05+07:00', $datetime->format('c'));
    }

    public function testMissingOptionalWithoutDefaultValueWillFail()
    {
        $this->expectException(DefaultValueException::class);
        $this->new(DateTime::class, ['timezone' => new DateTimeZone('UTC')]);
    }

    public function testContainerCanHandleNonExistingClass()
    {
        $this->expectException(InvalidDefinitionException::class);

        $container = new Container();
        $container->set('someclassthatdoesnotexist', ['testing']);
        $container->get('someclassthatdoesnotexist');
    }

    public function testWillUseDefaultValueForOptionalParameter()
    {
        $result = $this->call(function (?TestInterface $test1 = null, TestInterface $test2) {
            return $test1 ? $test1->implementation('works') : $test2->implementation('works');
        }, [
            'test2' => new TestImplementation2(new TestImplementation()),
        ]);

        $this->assertEquals('xxx:WORKS:xxx', $result);
    }

    public function testWillUseOverrideValueForOptionalParameter()
    {
        $result = $this->call(function (?TestInterface $test1 = null, TestInterface $test2) {
            return $test1 ? $test1->implementation('works') : $test2->implementation('works');
        }, [
            'test1' => new TestImplementation(),
            'test2' => new TestImplementation2(new TestImplementation()),
        ]);

        $this->assertEquals('WORKS', $result);
    }

    public function testWillUseContainerValueForOptionalParameter()
    {
        /**
         * @var Container $container
         */
        $container = $this->autowire->getContainer();
        $container->set(TestInterface::class, TestImplementation::class);
        $result = $this->call(function (?TestInterface $test1 = null, TestInterface $test2) {
            return $test1 ? $test1->implementation('works') : $test2->implementation('works');
        }, [
            'test2' => new TestImplementation2(new TestImplementation()),
        ]);

        $this->assertEquals('WORKS', $result);
    }

    public function testWillUseOverrideValueForOptionalParameterWithContainer()
    {
        /**
         * @var Container $container
         */
        $container = $this->autowire->getContainer();
        $container->set(TestInterface::class, TestImplementation2::class);
        $result = $this->call(function (?TestInterface $test1 = null, TestInterface $test2) {
            return $test1 ? $test1->implementation('works') : $test2->implementation('works');
        }, [
            'test1' => new TestImplementation(),
            'test2' => new TestImplementation2(new TestImplementation()),
        ]);

        $this->assertEquals('WORKS', $result);
    }
}
