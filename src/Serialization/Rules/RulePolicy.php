<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ApiSutra\Localization\Message;
use ApiSutra\Config\DateTimeHydrationPolicy;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Enums\DataTransfer\EmptyStringBehavior;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

final readonly class RulePolicy
{
    /** @param array<string, HandlerSpec> $casts */
    public function __construct(
        public ?ScalarPolicy $scalars = null,
        public ?EmptyStringBehavior $emptyString = null,
        public ?NamingStrategy $naming = null,
        public ?DateTimeHydrationPolicy $dateTime = null,
        public array $casts = [],
    ) {
        foreach ($casts as $type => $spec) {
            $this->validateCast($type, $spec);
        }
    }

    /** Проверяет фактические элементы PHP-массива, чьи generic-типы существуют только в PHPDoc. */
    private function validateCast(mixed $type, mixed $spec): void
    {
        if (!is_string($type) || $type === '' || !$spec instanceof HandlerSpec) {
            throw new ConfigurationException(new Message('serialization.rulepolicy_casts_requires_a_type_name_and_handlerspec'));
        }
    }

    /** @internal Верхняя policy наследует только незаданные значения. */
    public function over(self $lower): self
    {
        return new self(
            $this->scalars ?? $lower->scalars,
            $this->emptyString ?? $lower->emptyString,
            $this->naming ?? $lower->naming,
            $this->dateTime ?? $lower->dateTime,
            $this->casts + $lower->casts,
        );
    }
}
