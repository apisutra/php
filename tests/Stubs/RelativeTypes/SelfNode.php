<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\RelativeTypes;

use ApiSutra\DataTransfer\AbstractDto;

readonly class SelfNode extends AbstractDto
{
    public function __construct(public ?self $child = null, public int $id = 7)
    {
    }
}
