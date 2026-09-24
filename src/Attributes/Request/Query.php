<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Request;

use Attribute;
use ApiSutra\Enums\Http\QueryArrayFormat;

#[Attribute(Attribute::TARGET_PROPERTY)]
readonly class Query
{
    public function __construct(
        public ?string $name = null,
        public ?QueryArrayFormat $arrayFormat = null,
        public ?bool $nullable = null,
    ) {
    }
}
