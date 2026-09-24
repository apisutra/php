<?php

declare(strict_types=1);

namespace Example\ResultErrors;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Extensions\ExtensionInterface;
use ApiSutra\Extensions\ExtensionContext;

final class BrokenResponseExtension implements ExtensionInterface
{
    public function getName(): string
    {
        return 'broken-response-demo';
    }

    public function register(ExtensionContext $context): void
    {
        $context->registerResponseHandler('application/json', new BrokenResponseHandler(), true);
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
