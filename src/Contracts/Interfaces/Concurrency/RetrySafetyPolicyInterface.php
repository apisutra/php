<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Concurrency;

use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\VO\Http\ProviderResponse;
use Throwable;

/** Необязательная политика запроса; null сохраняет безопасность по HTTP-методу. */
interface RetrySafetyPolicyInterface
{
    /** Вычисление без I/O; true не отменяет лимиты попыток, бюджета и replay тела. */
    public function isRetrySafe(HttpMethod $method, ?ProviderResponse $response, ?Throwable $exception): ?bool;
}
