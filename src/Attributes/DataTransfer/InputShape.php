<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\DataTransfer;

use ApiSutra\Serialization\Rules\ContainerShape;
use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class InputShape
{
    public function __construct(public ContainerShape $value)
    {
    }
}
