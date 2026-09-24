<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingExecution;

use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;

final readonly class OutputProfile implements DtoSerializationProfileInterface
{
    public function policy(): DtoSerializationPolicy
    {
        return new DtoSerializationPolicy();
    }

    public function casts(): array
    {
        return ['int' => OutputHandler::class];
    }
}
