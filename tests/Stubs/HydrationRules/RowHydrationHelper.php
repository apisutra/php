<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Context\HydrationContext;

final readonly class RowHydrationHelper
{
    /**
     * @param list<array<string, mixed>> $items
     * @return list<object>
     */
    public static function hydrate(array $items, ?HydrationContext $scope): array
    {
        return $scope === null
            ? Hydrator::default()->hydrateCollection($items, RecordDto::class)
            : $scope->hydrateCollection($items, RecordDto::class);
    }
}
