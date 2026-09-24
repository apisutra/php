<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\RelativeTypes;

use ApiSutra\DataTransfer\AbstractDto;

final readonly class NamedUnionNode extends AbstractDto
{
    public function __construct(public NamedUnionNode|string|null $child = null)
    {
    }
}
