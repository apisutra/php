<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingExecution;

use ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use ApiSutra\Serialization\Hydrator;

final class Node implements DtoInterface
{
    public function __construct(public mixed $child = null)
    {
    }

    public static function from(array|object $data): static
    {
        return Hydrator::default()->hydrate($data, static::class);
    }
}
