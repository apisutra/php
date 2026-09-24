<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use Attribute;
use LogicException;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class ForeignAttribute
{
    public function __construct()
    {
        throw new LogicException('Посторонний атрибут не должен создаваться');
    }
}
