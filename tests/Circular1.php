<?php

namespace Fas\Autowire\Tests;

class Circular1
{
    public function __construct(Circular2 $input)
    {
    }


    public static function test(Circular2 $input)
    {
    }
}
