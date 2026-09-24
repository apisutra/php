<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Localization;

use ApiSutra\DataTransfer\AbstractDto;

final readonly class ChildDto extends AbstractDto
{
    public function __construct(public int $count)
    {
    }
}
