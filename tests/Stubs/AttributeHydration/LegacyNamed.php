<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\AttributeHydration;

use ApiSutra\Attributes\DataTransfer\DtoHydrate;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Serialization\Rules\ScalarPolicy;

#[DtoHydrate(namingStrategy: NamingStrategy::SnakeCase, scalars: ScalarPolicy::Legacy)]
final readonly class LegacyNamed
{
    public function __construct(public int $recordId)
    {
    }
}
