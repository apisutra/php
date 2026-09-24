<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MappingExecution;

use ApiSutra\Contracts\Interfaces\Casting\SerializationCastInterface;
use ApiSutra\Serialization\Context\SerializationContext;
use Closure;

final class OutputHandler implements SerializationCastInterface
{
    public static ?Closure $action = null;
    public static int $constructed = 0;

    public function __construct()
    {
        self::$constructed++;
    }

    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        return self::$action === null ? $value : (self::$action)($value, $context);
    }
}
