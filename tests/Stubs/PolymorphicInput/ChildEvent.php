<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use ApiSutra\Attributes\DataTransfer\DtoVariants;

#[DtoVariants('second', ['leaf' => LeafEvent::class])]
readonly class ChildEvent extends BaseEvent
{
}
