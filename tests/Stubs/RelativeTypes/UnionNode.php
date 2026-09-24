<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\RelativeTypes;

use ApiSutra\DataTransfer\AbstractDto;

final readonly class UnionNode extends AbstractDto
{
    public function __construct(public self|string|null $child = null)
    {
    }
}
