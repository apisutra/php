<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\DtoHydrate;
use ApiSutra\Enums\Configuration\NamingStrategy;

#[DtoHydrate(namingStrategy: NamingStrategy::SnakeCase)]
final readonly class StrictNamed
{
    public function __construct(public int $recordId)
    {
    }
}
