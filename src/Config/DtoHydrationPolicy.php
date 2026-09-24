<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\Enums\DataTransfer\EmptyStringBehavior;
use ApiSutra\Enums\Configuration\NamingStrategy;

final readonly class DtoHydrationPolicy
{
    public NamingStrategy $namingStrategy;
    public DateTimeHydrationPolicy $dateTime;
    public EmptyStringBehavior $emptyStringBehavior;

    public function __construct(
        NamingStrategy $namingStrategy = NamingStrategy::None,
        ?DateTimeHydrationPolicy $dateTime = null,
        EmptyStringBehavior $emptyStringBehavior = EmptyStringBehavior::Keep,
    ) {
        $this->namingStrategy = $namingStrategy;
        $this->dateTime = $dateTime ?? new DateTimeHydrationPolicy();
        $this->emptyStringBehavior = $emptyStringBehavior;
    }

    public function merge(self $override): self
    {
        return new self(
            namingStrategy: $override->namingStrategy,
            dateTime: $override->dateTime,
            emptyStringBehavior: $override->emptyStringBehavior,
        );
    }
}
