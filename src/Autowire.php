<?php

declare(strict_types=1);

namespace Fas\Autowire;

use Closure;
use Fas\Autowire\Exception\CircularDependencyException;
use Fas\Autowire\Exception\DefaultValueException;
use Fas\Autowire\Exception\NotFoundException;
use InvalidArgumentException;
use Opis\Closure\ReflectionClosure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Throwable;

class Autowire
{
    private ?ContainerInterface $container;
    private ?ReferenceTrackerInterface $referenceTracker = null;
    private array $newing = [];

    public function __construct(?ContainerInterface $container = null)
    {
        $this->container = $container ?? new Container($this);
    }

    public function setReferenceTracker(ReferenceTrackerInterface $referenceTracker)
    {
        $this->referenceTracker = $referenceTracker;
    }

    public function trackReference($id)
    {
        if ($this->referenceTracker) {
            $this->referenceTracker->trackReference($id);
        }
    }

    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    public function canAutowire($className, array $args = [])
    {
        if (!class_exists($className) || !(new ReflectionClass($className))->isInstantiable()) {
            return false;
        }
        try {
            $this->markNewing($className);
            $c = new ReflectionClass($className);
            if ($r = $c->getConstructor()) {
                $this->createArguments($r, $args, $className);
            }
            return true;
        } catch (CircularDependencyException $e) {
            throw $e;
        } catch (Throwable $e) {
            return false;
        } finally {
            $this->unmarkNewing($className);
        }
    }

    public function createArguments(ReflectionFunctionAbstract $r, array $namedargs = [], $id = null)
    {
        $args = [];
        $ps = $r->getParameters();
        $errors = null;
        foreach ($ps as $p) {
            $name = $p->getName();

            // Named parameters first
            if (array_key_exists($name, $namedargs)) {
                if ($errors) {
                    throw $errors;
                }

                if ($p->isPassedByReference()) {
                    if ($p->isVariadic()) {
                        foreach ($namedargs[$name] as &$value) {
                            $args[] = &$value;
                        }
                    } else {
                        $args[] = &$namedargs[$name];
                    }
                } else {
                    if ($p->isVariadic()) {
                        array_push($args, ...$namedargs[$name]);
                    } else {
                        $args[] = $namedargs[$name];
                    }
                }
                continue;
            }

            $types = $p->hasType() ? $p->getType() : [];
            $types = $types && $types instanceof ReflectionUnionType ? $types->getTypes() : [$types];
            $types = array_filter($types, function ($type) {
                return $type instanceof ReflectionNamedType;
            });
            $types = array_map(function ($type) {
                return $type->getName();
            }, $types);

            foreach ($types as $type) {
                if (isset($type) && $this->container->has($type)) {
                    if ($errors) {
                        throw $errors;
                    }

                    $this->trackReference($type);
                    $args[] = $this->container->get($type);
                    continue 2;
                }
            }

            if ($p->isDefaultValueAvailable()) {
                if ($errors) {
                    throw $errors;
                }

                $arg = $p->getDefaultValue();
                $args[] = &$arg;
            } elseif ($p->isOptional()) {
                $errors = new DefaultValueException($id, DefaultValueException::argument($p), $errors);
            } else {
                throw new DefaultValueException($id, DefaultValueException::argument($p), $errors);
            }
        }
        return $args;
    }

    public function compileArguments(ReflectionFunctionAbstract $r, array $defaultArguments = [], bool $callArguments = true)
    {
        $args = [];
        $ps = $r->getParameters();
        foreach ($ps as $p) {
            $name = $p->getName();

            $inputValue = $inputValueOr = "";
            if ($callArguments) {
                $inputValue = '$args[' . var_export($name, true) . ']';
                $inputValueOr = "$inputValue ?? ";
            }

            $hasDefaultValue = null;
            if (isset($defaultArguments[$name])) {
                $defaultValue = var_export($defaultArguments[$name], true);
                $hasDefaultValue = true;
            } else {
                $types = $p->hasType() ? $p->getType() : [];
                $types = $types && $types instanceof ReflectionUnionType ? $types->getTypes() : [$types];
                $types = array_filter($types, function ($type) {
                    return $type instanceof ReflectionNamedType;
                });
                $types = array_map(function ($type) {
                    return $type->getName();
                }, $types);

                foreach ($types as $type) {
                    if ($this->container->has($type) || interface_exists($type)) {
                        $this->trackReference($type);
                        $defaultValue = '$container->get(' . var_export($type, true) . ')';
                        if ($p->isDefaultValueAvailable()) {
                            $defaultValue = '($container->has(' . var_export($type, true) . ') ? ' . $defaultValue . ' : ' . var_export($p->getDefaultValue(), true) . ')';
                        }
                        $hasDefaultValue = true;
                    break;
                    }
                }
            }

            if (!$hasDefaultValue) {
                if ($p->isDefaultValueAvailable()) {
                    $defaultValue = var_export($p->getDefaultValue(), true);
                    $hasDefaultValue = true;
                } else {
                    $source = DefaultValueException::argument($p);
                    $hasDefaultValue = false;
                }
            }

            if ($p->isPassedByReference()) {
                if ($callArguments) {
                    $inputValueOr = '';
                }
                if ($hasDefaultValue) {
                    $args[$name] = '\\' . self::class . '::byReferenceWithDefaultValue($args, ' . var_export($name, true) . ', ' . $defaultValue . ')';
                } else {
                    $args[$name] = '\\' . self::class . '::byReferenceWithoutDefaultValue($args, ' . var_export($name, true) . ', ' . var_export($source, true) . ')';
                }
            } elseif ($hasDefaultValue) {
                $args[$name] = $defaultValue;
            } elseif ($callArguments) {
                $args[$name] = '\\' . DefaultValueException::class . '::throw("", ' . var_export($source, true) . ')';
            }
            if (isset($args[$name])) {
                $args[$name] = $inputValueOr . $args[$name];
                if ($p->isVariadic()) {
                    $args[$name] = '...' . $args[$name];
                }
            }
        }
        return $args;
    }

    public static function &byReferenceWithDefaultValue(&$args, $name, $default)
    {
        if (isset($args[$name])) {
            $param = &$args[$name];
        } else {
            $param = $default;
        }
        return $param;
    }

    public static function &byReferenceWithoutDefaultValue(&$args, $name, $source)
    {
        if (isset($args[$name])) {
            $param = &$args[$name];
        } else {
            throw new DefaultValueException('', $source);
        }
        return $param;
    }

    private function markNewing(string $className)
    {
        if (isset($this->newing[$className])) {
            throw new CircularDependencyException(array_keys($this->newing + [$className => true]));
        }
        $this->newing[$className] = true;
    }

    private function unmarkNewing(string $className)
    {
        unset($this->newing[$className]);
    }

    public function new(string $className, array $namedargs = [])
    {
        if (!class_exists($className)) {
            throw new NotFoundException($className);
        }

        try {
            $this->markNewing($className);
            $c = new ReflectionClass($className);
            $r = $c->getConstructor();
            $args = $r ? $this->createArguments($r, $namedargs, $className) : [];
            return $c->newInstanceArgs($args);
        } finally {
            $this->unmarkNewing($className);
        }
    }

    public function compileNew(string $className, array $defaultArguments = [], bool $callArguments = true)
    {
        if (!class_exists($className)) {
            throw new NotFoundException($className);
        }

        $c = new ReflectionClass($className);
        $r = $c->getConstructor();
        $args = $r ? $this->compileArguments($r, $defaultArguments, $callArguments) : [];
        $code = "return new \\" . $c->getName() . '(' . implode(', ', $args) . ');';
        return new CompiledClosure($code);
    }

    public function call($callback, array $namedArgs = [])
    {
        if (is_array($callback) && is_string($callback[0])) {
            [$class, $method] = $callback;
            if (!class_exists($class) && !interface_exists($class)) {
                $instance = $this->container->get($class);
                $class = get_class($instance);
            }

            $reflection = new ReflectionMethod($class, $method);
            $args = $this->createArguments($reflection, $namedArgs, $class);
            if ($reflection->isStatic()) {
                return $reflection->invokeArgs(null, $args);
            } else {
                return $reflection->invokeArgs($instance ?? $this->container->get($class), $args);
            }
        }

        if (is_string($callback) && $this->container->has($callback)) {
            $class = $callback;
            $instance = $this->container->get($class);
            if (!is_callable($instance)) {
                throw new InvalidArgumentException("'$class' is not callable (missing __invoke)");
            }
            $reflection = new ReflectionMethod($class, '__invoke');
            $args = $this->createArguments($reflection, $namedArgs);
            return $reflection->invokeArgs($instance, $args);
        }

        if (is_callable($callback)) {
            $closure = Closure::fromCallable($callback);
            $reflection = new ReflectionFunction($closure);
            $args = $this->createArguments($reflection, $namedArgs);
            return $reflection->invokeArgs($args);
        }

        throw new InvalidArgumentException("Cannot invoke callback");
    }

    public function compileCall($callback, array $defaultArguments = [], bool $callArguments = true)
    {
        if (is_array($callback) && is_object($callback[0])) {
            [$instance, $method] = $callback;
            $class = get_class($instance);
            throw new InvalidArgumentException("Cannot compile instantiated object: \\$class->$method()");
        }

        if (is_array($callback) && is_string($callback[0])) {
            [$class, $method] = $callback;
            if (!class_exists($class) && !interface_exists($class)) {
                $instance = $this->container->get($class);
                $class = get_class($instance);
            }

            $reflection = new ReflectionMethod($class, $method);
            $args = $this->compileArguments($reflection, $defaultArguments, $callArguments);
            if ($reflection->isStatic()) {
                $code = "\\$class::$method(" . implode(',', $args) . ')';
            } else {
                $code = '$container->get(' . var_export($class, true) . ")->$method(" . implode(',', $args) . ')';
            }
        } elseif (is_string($callback) && $this->container->has($callback)) {
            $class = $callback;
            if (!method_exists($class, '__invoke')) {
                throw new InvalidArgumentException("'$class' is not callable (missing __invoke)");
            }
            $reflection = new ReflectionMethod($class, '__invoke');
            $args = $this->compileArguments($reflection, $defaultArguments, $callArguments);
            $code = '(' . '$container->get(' . var_export($class, true) . "))(" . implode(',', $args) . ')';
        } elseif (is_callable($callback)) {
            $closure = Closure::fromCallable($callback);
            $reflection = new ReflectionClosure($closure);
            $args = $this->compileArguments($reflection, $defaultArguments, $callArguments);
            $staticVariables = [];
            foreach ($reflection->getStaticVariables() as $key => $value) {
                $staticVariables[] = "\$$key = " . var_export($value, true) . ';';
            }
            $staticVariables = implode("\n", $staticVariables);
            $code = '(' . $reflection->getCode() . ')(' . implode(',', $args) . ')';
        } else {
            throw new InvalidArgumentException("Cannot compile callback");
        }

        $code = 'return ' . $code . ';';
        if (!empty($staticVariables)) {
            $code = $staticVariables . "\n" . $code;
        }
        return new CompiledClosure($code);
    }
}
