<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\Extras;
use Stringable;

final readonly class StringReceiverDto implements Stringable
{
    public function __construct(#[Extras] public array $extra = [])
    {
    }
    public function __toString(): string
    {
        return 'opaque';
    }
}
