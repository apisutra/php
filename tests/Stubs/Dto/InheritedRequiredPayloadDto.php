<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\From;

final readonly class InheritedRequiredPayloadDto extends InheritedBaseStatusDto
{
    #[From('result')]
    public NestedItemDto $result;
}
