<?php

namespace Fas\Autowire\Tests;

interface TestInterface
{
    public function id();
    public function implementation($name = 'abc');
}
