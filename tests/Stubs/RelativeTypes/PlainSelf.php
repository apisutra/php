<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\RelativeTypes;

final readonly class PlainSelf
{
    public function __construct(public ?self $child = null, public int $id = 7)
    {
    }
}
