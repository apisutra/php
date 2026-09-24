<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\RelativeTypes;

use ApiSutra\DataTransfer\AbstractDto;

readonly class BaseRecord extends AbstractDto
{
    public function __construct(public int $id = 7)
    {
    }
}
