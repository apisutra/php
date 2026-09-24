<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingHydration;

final readonly class PlainDto
{
    public function __construct(public int $id = 0, public array $rows = [], public array $_extra = [])
    {
    }
}
