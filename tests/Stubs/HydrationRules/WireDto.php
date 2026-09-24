<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\DataTransfer\AbstractDto;

final readonly class WireDto extends AbstractDto
{
    public function __construct(public int $id = 7, public array $extra = [])
    {
        WireCounter::$constructed++;
    }
}
