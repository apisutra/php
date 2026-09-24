<?php

declare(strict_types=1);

namespace Example\DtoShowcase;

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;

final class CatalogHydration
{
    public static function create(): HydrationConfig
    {
        return new HydrationConfig(policy: new RulePolicy(scalars: ScalarPolicy::Strict));
    }
}
