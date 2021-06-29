<?php

namespace Fas\Autowire\Tests;

class Circular2
{
    public function __construct(Circular1 $input)
    {
    }
}
