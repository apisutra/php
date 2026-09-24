<?php

declare(strict_types=1);

namespace ApiSutra\Casts;

use ApiSutra\Localization\Message;
use ApiSutra\Config\DateTimeHydrationPolicy;
use ApiSutra\Config\DateTimeSerializationPolicy;
use ApiSutra\Contracts\Interfaces\Casting\CastInterface;
use ApiSutra\Enums\Serialization\DateTimeInvalidBehavior;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Context\SerializationContext;
use DateTimeImmutable;
use DateTimeInterface;
use DateTime;
use DateTimeZone;
use Override;
use Stringable;
use Throwable;

final class DateTimeCast implements CastInterface
{
    private const string OFFSET_PATTERN = '/(Z|[+-]\d{2}(:?\d{2})?)$/';

    public function __construct(
        private readonly string $format = DATE_ATOM,
        private readonly ?string $timezone = null,
        private readonly ?DateTimeHydrationPolicy $hydratePolicy = null,
        private readonly ?DateTimeSerializationPolicy $serializePolicy = null,
    ) {
    }

    public static function fromHydrationPolicy(DateTimeHydrationPolicy $policy): self
    {
        return new self(hydratePolicy: $policy);
    }

    public static function fromSerializationPolicy(DateTimeSerializationPolicy $policy): self
    {
        return new self(serializePolicy: $policy);
    }

    #[Override]
    public function hydrate(mixed $value, HydrationContext $context): ?DateTimeInterface
    {
        if ($value === null || $value === '') {
            return null;
        }

        $parse = $this->hydratePolicy;
        $format = $parse->format ?? $this->format;
        $timezoneName = $parse !== null ? $parse->defaultTimezone : $this->timezone;
        $timezone = $this->resolveTimezone($timezoneName);
        if (!is_scalar($value) && !$value instanceof Stringable) {
            return $this->handleInvalid($parse, $value);
        }
        $stringValue = (string) $value;
        $hasOffset = $this->hasOffset($stringValue);

        try {
            $date = DateTimeImmutable::createFromFormat($format, $stringValue, $timezone);
            if ($date === false) {
                if ($parse?->strictFormat) {
                    return $this->handleInvalid($parse, $value);
                }
                $date = new DateTimeImmutable($stringValue, $timezone);
            }
        } catch (HydrationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            return $this->handleInvalid($parse, $value, $exception);
        }

        if (!$hasOffset && $parse?->strictMissingTimezone) {
            return $this->handleInvalid($parse, $value);
        }
        if ($hasOffset && $parse !== null && !$parse->preserveOffset && $timezone !== null) {
            $date = $date->setTimezone($timezone);
        }

        return $date;
    }

    #[Override]
    public function serialize(mixed $value, SerializationContext $context): ?string
    {
        if ($value === null) {
            return null;
        }

        if (!$value instanceof DateTimeInterface) {
            throw new ConfigurationException(new Message('casts.datetimecast_serialize_expects_datetimeinterface'));
        }

        /** @var DateTime|DateTimeImmutable $date Реализации DateTimeInterface предоставляются PHP. */
        $date = $value;

        if ($this->serializePolicy === null) {
            return $date->format($this->format);
        }

        $format = $this->serializePolicy->format;
        $timezone = $this->serializePolicy->timezone;

        if ($timezone !== null) {
            $date = $date->setTimezone(new DateTimeZone($timezone));
        }

        return $date->format($format);
    }

    private function hasOffset(string $value): bool
    {
        return (bool) preg_match(self::OFFSET_PATTERN, $value);
    }

    private function resolveTimezone(?string $timezone): ?DateTimeZone
    {
        try {
            return $timezone ? new DateTimeZone($timezone) : null;
        } catch (Throwable $exception) {
            throw new ConfigurationException(new Message('casts.invalid_timezone_in_datetimecast_configuration'), previous: $exception);
        }
    }

    private function handleInvalid(
        ?DateTimeHydrationPolicy $config,
        mixed $value,
        ?Throwable $exception = null,
    ): ?DateTimeInterface {
        if ($config?->invalidBehavior === DateTimeInvalidBehavior::Null) {
            return null;
        }

        throw HydrationException::invalidValue(
            'invalid_datetime',
            'date: ' . ($config->format ?? $this->format),
            get_debug_type($value),
            previous: $exception,
        );
    }
}
