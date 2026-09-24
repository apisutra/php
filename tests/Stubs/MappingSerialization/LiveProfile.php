<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingSerialization;

use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Contracts\Interfaces\Casting\SerializationCastInterface;
use ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;

final class LiveProfile implements DtoSerializationProfileInterface
{
    public static DtoSerializationPolicy $current;
    /** @var array<string, SerializationCastInterface> */
    public static array $handlers = [];
    /** @var list<string> */
    public static array $trace = [];

    public function policy(): DtoSerializationPolicy
    {
        self::$trace[] = 'policy';
        return self::$current;
    }

    public function casts(): array
    {
        self::$trace[] = 'casts';
        return self::$handlers;
    }
}
