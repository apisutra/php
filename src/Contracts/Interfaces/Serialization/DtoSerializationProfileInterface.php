<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Serialization;

use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Contracts\Interfaces\Casting\SerializationCastInterface;

interface DtoSerializationProfileInterface
{
    public function policy(): DtoSerializationPolicy;

    /**
     * @return array<string, HydrationCastInterface|SerializationCastInterface|class-string<HydrationCastInterface|SerializationCastInterface>>
     */
    public function casts(): array;
}
