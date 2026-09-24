<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Extensions;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Extensions\ExtensionInterface;
use ApiSutra\Extensions\ExtensionContext;

final class TestResponseExtension implements ExtensionInterface
{
    public static int $bootCount = 0;

    public static bool $enabled = true;

    public static function reset(): void
    {
        self::$bootCount = 0;
        self::$enabled = true;
    }

    public function getName(): string
    {
        return 'test-response-extension';
    }

    public function register(ExtensionContext $context): void
    {
        $context->registerResponseHandler('application/json', new TestResponseHandler(), true);
    }

    public function boot(ClientConfig $config): void
    {
        self::$bootCount++;
    }

    public function checkDependencies(): void
    {
    }

    public function isEnabled(): bool
    {
        return self::$enabled;
    }
}
