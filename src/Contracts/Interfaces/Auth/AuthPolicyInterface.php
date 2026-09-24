<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Auth;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;

/**
 * Политика выбора запросов, к которым применять auth.
 */
interface AuthPolicyInterface
{
    /**
     * @return array<class-string<RequestInterface>>
     */
    public function allowedRequests(): array;
}
