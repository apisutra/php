<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Serialization;

use ApiSutra\Config\DtoHydrationPolicy;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Contracts\Interfaces\Casting\SerializationCastInterface;

interface DtoHydrationProfileInterface
{
    public function policy(): DtoHydrationPolicy;

    /**
     * @return array<string, HydrationCastInterface|SerializationCastInterface|class-string<HydrationCastInterface|SerializationCastInterface>>
     */
    public function casts(): array;
}
