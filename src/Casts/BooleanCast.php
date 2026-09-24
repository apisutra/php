<?php

declare(strict_types=1);

namespace ApiSutra\Casts;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use ApiSutra\Enums\Serialization\BooleanFormat;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use Override;

final class BooleanCast implements CastInterface
{
    /** Без формата сохраняется исходное приведение в bool, включая DTO. */
    public function __construct(private readonly ?BooleanFormat $format = null)
    {
    }

    #[Override]
    public function hydrate(mixed $value, HydrationContext $context): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (bool) ((int) $value);
        }

        $normalized = strtolower((string) $value);
        return in_array($normalized, ['1', 'true', 'yes', 'y'], true);
    }

    #[Override]
    public function serialize(mixed $value, SerializationContext $context): mixed
    {
        if ($this->format === null || $value === null) {
            return $value === null ? null : (bool) $value;
        }
        if (!is_bool($value)) {
            throw new SerializationException(new Message('casts.text_booleancast_expects_bool_or_null'));
        }

        return $this->format->format($value);
    }
}
