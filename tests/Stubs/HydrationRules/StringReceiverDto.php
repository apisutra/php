<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use Stringable;

final readonly class StringReceiverDto implements Stringable
{
    public function __construct(public array $extra = [])
    {
    }
    public function __toString(): string
    {
        return 'opaque';
    }
}
