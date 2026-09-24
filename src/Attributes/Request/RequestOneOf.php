<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Request;

use Attribute;
use ApiSutra\Enums\Request\OneOfMode;

#[Attribute(Attribute::TARGET_CLASS | Attribute::IS_REPEATABLE)]
readonly class RequestOneOf
{
    /**
     * @param array<string, array<int, string>> $variants
     * @param array<int, string> $requiredCommon
     */
    public function __construct(
        public string $name,
        public array $variants,
        public array $requiredCommon = [],
        public OneOfMode $mode = OneOfMode::ExactlyOne,
    ) {
    }
}
