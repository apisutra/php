<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_PROPERTY)]
final readonly class TagAttribute
{
    public function __construct(
        public string $value,
    ) {}
}
