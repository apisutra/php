<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

final readonly class AmbiguousEvent
{
    public function __construct(public Event|BaseEvent $value)
    {
    }
}
