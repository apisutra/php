<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingExecution;

use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Serialization\Context\HydrationContext;
use Closure;

final class InputHandler implements HydrationCastInterface
{
    public static ?Closure $action = null;
    public static int $constructed = 0;

    public function __construct()
    {
        self::$constructed++;
    }

    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        return self::$action === null ? $value : (self::$action)($value, $context);
    }
}
