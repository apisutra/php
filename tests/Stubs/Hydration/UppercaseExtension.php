<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Hydration;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Extensions\ExtensionInterface;
use ApiSutra\Extensions\ExtensionContext;
use ApiSutra\Tests\Stubs\Casts\UppercaseCast;

final readonly class UppercaseExtension implements ExtensionInterface
{
    public function getName(): string
    {
        return 'hydration-cast-contract';
    }

    public function register(ExtensionContext $context): void
    {
        $context->registerCast('string', new UppercaseCast());
    }

    public function boot(ClientConfig $config): void
    {
    }

    public function checkDependencies(): void
    {
    }

    public function isEnabled(): bool
    {
        return true;
    }
}
