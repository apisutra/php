<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingExecution;

use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Serialization\Context\HydrationContext;
use Closure;

final class Provider implements DefaultValueProviderInterface
{
    public static ?Closure $action = null;

    public function resolve(mixed $value, ValueState $state, array $source, HydrationContext $context): mixed
    {
        return (self::$action)($value, $state, $source, $context);
    }
}
