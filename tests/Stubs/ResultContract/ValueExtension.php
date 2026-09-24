<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ResultContract;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Extensions\ExtensionInterface;
use ApiSutra\Extensions\ExtensionContext;

final readonly class ValueExtension implements ExtensionInterface
{
    public function __construct(private mixed $value)
    {
    }
    public function getName(): string
    {
        return 'result-contract';
    }
    public function register(ExtensionContext $context): void
    {
        $context->registerResponseHandler('application/json', new ValueHandler($this->value), true);
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
