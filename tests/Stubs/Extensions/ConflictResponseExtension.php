<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Extensions;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Extensions\ExtensionInterface;
use ApiSutra\Extensions\ExtensionContext;

final class ConflictResponseExtension implements ExtensionInterface
{
    public function getName(): string
    {
        return 'conflict-response-extension';
    }

    public function register(ExtensionContext $context): void
    {
        $context->registerResponseHandler('application/json', new TestResponseHandler(), false);
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
