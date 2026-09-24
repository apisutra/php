<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Request;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class RequestDiscriminator
{
    /**
     * @param array<string, string> $map
     */
    public function __construct(
        public string $field,
        public array $map,
    ) {
    }
}
