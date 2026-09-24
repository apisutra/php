<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Request\RequestSpecResolver;
use ApiSutra\Support\ContainerProviderRegistry;
use ApiSutra\Testing\MockClient;
use ApiSutra\VO\Validation\Validator;

trait TestTrait
{
    use AssertHelpers;

    protected function resetApiSutraState(): void
    {
        MockClient::destroyGlobal();
        Validator::resetFactory();
        ContainerProviderRegistry::reset();
        RequestSpecResolver::clearCache();
    }
}
