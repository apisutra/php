<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Contracts\Interfaces\Concurrency\RetrySafetyPolicyInterface;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\VO\Http\ProviderResponse;
use Throwable;

class ConditionalRetryRequest extends RetryPolicyRequest implements RetrySafetyPolicyInterface
{
    /** @var list<array{?int, ?Throwable}> */
    public array $safetyChecks = [];
    public ?Throwable $safetyFailure = null;

    public function isRetrySafe(HttpMethod $method, ?ProviderResponse $response, ?Throwable $exception): ?bool
    {
        $this->safetyChecks[] = [$response?->status, $exception];
        if ($this->safetyFailure !== null) {
            throw $this->safetyFailure;
        }
        return $method === HttpMethod::POST ? $exception === null && $response?->status === 429 : null;
    }
}
