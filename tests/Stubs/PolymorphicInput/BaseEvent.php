<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use ApiSutra\Attributes\DataTransfer\DtoVariants;

#[DtoVariants('type', ['child' => ChildEvent::class], unknown: self::class)]
readonly class BaseEvent
{
    public function __construct(public string $label = 'base')
    {
    }
}
