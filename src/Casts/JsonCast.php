<?php

declare(strict_types=1);

namespace ApiSutra\Casts;

use ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\JsonEncoder;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use JsonException;
use Override;

final class JsonCast implements CastInterface
{
    #[Override]
    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        if ($value === null) {
            return null;
        }

        if (is_string($value)) {
            try {
                return json_decode($value, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
            } catch (JsonException $exception) {
                throw HydrationException::invalidValue('invalid_json', 'valid JSON', 'string', previous: $exception);
            }
        }

        return $value;
    }

    #[Override]
    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        if ($value === null) {
            return null;
        }

        return JsonEncoder::encode($value);
    }
}
