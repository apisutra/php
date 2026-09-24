<?php

declare(strict_types=1);

namespace ApiSutra\Localization;

use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Exceptions\Core\InvalidArgumentException;

/** Описание сообщения не зависит от выбранного языка. */
final readonly class Message
{
    /** @param array<string, scalar|null|Message> $parameters */
    public function __construct(public string $key, public array $parameters = [])
    {
        if ($key === '') {
            throw new InvalidArgumentException(new self('localization.empty_key'));
        }
        self::validate($parameters);
    }

    private static function validate(mixed $parameters): void
    {
        foreach ($parameters as $name => $value) {
            if (!is_string($name) || (!is_scalar($value) && $value !== null && !$value instanceof self)) {
                throw new InvalidArgumentException(new self('localization.invalid_parameters'));
            }
        }
    }

    public function render(?LocalizationConfig $localization = null): string
    {
        return (new MessageFormatter($localization ?? new LocalizationConfig()))->format($this);
    }
}
