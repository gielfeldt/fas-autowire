<?php

namespace Fas\Autowire\Tests;

class TestImplementation2 implements TestInterface
{
    private TestImplementation $test;

    public function __construct(TestImplementation $test)
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
