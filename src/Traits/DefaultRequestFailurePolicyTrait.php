<?php

declare(strict_types=1);

namespace ApiSutra\Traits;

use ApiSutra\VO\Http\ProviderResponse;
use Throwable;

/**
 * Дефолтная политика определения ошибок запроса.
 */
trait DefaultRequestFailurePolicyTrait
{
    protected function hasRequestFailed(ProviderResponse $response): bool
    {
        return $response->status >= 400;
    }

    protected function shouldRetry(ProviderResponse $response, int $attempt): bool
    {
        return false;
    }

    protected function getRequestException(ProviderResponse $response): ?Throwable
    {
        return null;
    }
}
