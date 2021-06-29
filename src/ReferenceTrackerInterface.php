<?php

namespace Fas\Autowire;

interface ReferenceTrackerInterface
{
    public function trackReference(string $id);
}
