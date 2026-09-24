<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\VO;

use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\DtoSerializationPolicy;

final readonly class ResolvedDtoSerialization
{
    public function __construct(
        public DtoSerializationPolicy $policy,
        public CastRegistry $casts,
    ) {
    }
}
