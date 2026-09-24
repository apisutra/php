<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Transport;

use ApiSutra\Config\CooldownConfig;

/** @internal Область привязана к конкретному подготовленному запросу. */
final readonly class ResolvedCooldown
{
    public function __construct(public CooldownConfig $config, public string $key)
    {
    }
}
