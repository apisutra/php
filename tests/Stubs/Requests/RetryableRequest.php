<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Behavior\Retry;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\RateLimiting\BackoffStrategy;

#[Get('/retryable')]
#[Retry(attempts: 2, baseDelay: 10, maxDelay: 50, backoff: BackoffStrategy::Constant, jitter: false)]
final class RetryableRequest extends AbstractRequest
{
    public function __construct(
        #[Query]
        public string $payload,
    ) {}
}
