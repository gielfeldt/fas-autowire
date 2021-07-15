<?php

namespace Fas\Autowire\Tests;

class TestImplementation3 implements TestInterface
{
    private TestInterface $test;

    public function __construct(TestInterface $test)
    {
        $this->test = $test;
    }

    public function id()
    {
        return $this->test->id();
    }

    public function implementation($name = 'abc')
    {
        return 'xxx:' . $this->test->implementation($name) . ':xxx';
    }
}
