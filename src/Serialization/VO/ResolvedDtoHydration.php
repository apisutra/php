<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\VO;

use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\DtoHydrationPolicy;

final readonly class ResolvedDtoHydration
{
    public function __construct(
        public DtoHydrationPolicy $policy,
        public CastRegistry $casts,
    ) {
    }
}
