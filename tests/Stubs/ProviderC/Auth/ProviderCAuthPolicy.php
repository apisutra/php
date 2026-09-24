<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ProviderC\Auth;

use ApiSutra\Contracts\Interfaces\Auth\AuthPolicyInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Tests\Stubs\ProviderC\Requests\ProviderCSystemOrgCheckRequest;
use ApiSutra\Tests\Stubs\ProviderC\Requests\ProviderCSystemPeopleCheckRequest;

/**
 * Политика auth для Provider C: только системные запросы.
 */
final readonly class ProviderCAuthPolicy implements AuthPolicyInterface
{
    public function allowedRequests(): array
    {
        return [
            ProviderCSystemPeopleCheckRequest::class,
            ProviderCSystemOrgCheckRequest::class,
        ];
    }
}
