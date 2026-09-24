<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\HydrationRules;

use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use ApiSutra\Serialization\Integration\HttpMappingContext;

final class CountedRecordCast implements CastInterface
{
    /** @var list<HttpMappingContext|null> */
    public static array $contexts = [];

    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        self::$contexts[] = $context->extension(HttpMappingContext::class);
        return $context->hydrate($value, CountedRecordDto::class);
    }

    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        return $value;
    }
}
