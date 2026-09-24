<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\RelativeTypes;

use ApiSutra\DataTransfer\AbstractDto;

final readonly class NamedNode extends AbstractDto
{
    public function __construct(public ?NamedNode $child = null, public int $id = 7)
    {
    }
}
