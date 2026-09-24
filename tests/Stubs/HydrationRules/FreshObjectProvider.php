<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Enums\DataTransfer\ValueState;
use stdClass;

final readonly class FreshObjectProvider implements DefaultValueProviderInterface
{
    public function resolve(mixed $value, ValueState $state, array $source, HydrationContext $context): mixed
    {
        return new stdClass();
    }
}
