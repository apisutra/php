<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ResultContract;

readonly class ContractDto
{
    public function __construct(public int $id = 1)
    {
    }
}
