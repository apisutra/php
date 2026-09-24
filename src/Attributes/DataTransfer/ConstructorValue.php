<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\DataTransfer;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ConstructorValue
{
    public function __construct(public bool $allowMissing = false)
    {
    }
}
