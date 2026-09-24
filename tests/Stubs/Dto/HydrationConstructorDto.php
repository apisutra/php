<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Support\HydrationConstructionProbe;

final readonly class HydrationConstructorDto extends AbstractDto
{
    public int $id;

    /** @param array{id: int} $id */
    public function __construct(array $id)
    {
        HydrationConstructionProbe::$calls++;
        $this->id = $id['id'];
    }
}
