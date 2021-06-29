<?php

namespace Fas\Autowire\Tests;

use Fas\Autowire\ReferenceTrackerInterface;

class TestReferenceTracker implements ReferenceTrackerInterface
{
    public array $references = [];

    public function trackReference(string $id)
    {
        $this->references[$id] = true;
    }
}
