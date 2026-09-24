<?php

declare(strict_types=1);

namespace Example\Records\Resources\Records\Get;

use ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use Override;

// Учебный API передаёт длительность как MM:SS: 2–6 цифр минут и секунды 00–59.
final readonly class ReadingTimeCast implements CastInterface
{
    #[Override]
    public function hydrate(mixed $value, HydrationContext $context): mixed
    {
        if (!is_string($value) || preg_match('/^([0-9]{2,6}):([0-5][0-9])$/D', $value, $parts) !== 1) {
            throw HydrationException::invalidValue('invalid_reading_time', 'MM:SS string', get_debug_type($value));
        }

        return (int) $parts[1] * 60 + (int) $parts[2];
    }

    #[Override]
    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        if (!is_int($value) || $value < 0 || $value > 59_999_999) {
            throw new SerializationException('Ожидается длительность от 0 до 59999999 секунд');
        }

        return sprintf('%02d:%02d', intdiv($value, 60), $value % 60);
    }
}
