<?php

declare(strict_types=1);

namespace Fas\Autowire;

interface ReferenceTrackerInterface
{
    public function trackReference(string $id);
}
