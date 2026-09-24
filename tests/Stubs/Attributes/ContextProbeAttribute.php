<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class ContextProbeAttribute
{
    public function __construct(
        public string $value,
    ) {}
}
