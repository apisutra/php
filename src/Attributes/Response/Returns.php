<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Response;

use ApiSutra\Contracts\Interfaces\Serialization\DtoHydratorInterface;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Returns
{
    /** @param class-string<DtoHydratorInterface>|false|null $hydrator */
    public function __construct(
        public string $response,
        public ?string $unwrap = null,
        public ?string $type = null,
        public ?string $mismatchMessage = null,
        public string|false|null $hydrator = null,
    ) {
    }
}
