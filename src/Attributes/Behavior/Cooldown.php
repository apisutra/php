<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Behavior;

use ApiSutra\Config\CooldownConfig;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use Attribute;

/** Null наследует поле конфигурации клиента. */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Cooldown
{
    public function __construct(
        public ?bool $enabled = null,
        public ?string $group = null,
        public ?RateLimitBehavior $behavior = null,
        public ?int $maxAdditionalWaitMs = null,
    ) {
    }

    public function apply(CooldownConfig $config): CooldownConfig
    {
        return new CooldownConfig(
            $this->enabled ?? $config->enabled,
            $this->group ?? $config->group,
            $this->behavior ?? $config->behavior,
            $config->identity,
            $this->maxAdditionalWaitMs ?? $config->maxAdditionalWaitMs,
        );
    }
}
