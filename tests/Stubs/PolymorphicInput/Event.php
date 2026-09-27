<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use ApiSutra\Attributes\DataTransfer\DtoVariants;

#[DtoVariants('type', ['message' => MessageEvent::class], unknown: RawEvent::class)]
interface Event
{
}
