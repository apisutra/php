<?php

declare(strict_types=1);

namespace ApiSutra\Request;

use ApiSutra\Attributes\Behavior\Cache;
use ApiSutra\Attributes\Behavior\Execution;
use ApiSutra\Attributes\Behavior\Idempotent;
use ApiSutra\Attributes\Behavior\Pagination;
use ApiSutra\Attributes\Behavior\RateLimit;
use ApiSutra\Attributes\Behavior\Cooldown;
use ApiSutra\Attributes\Behavior\Retry;
use ApiSutra\Attributes\Behavior\Timeout;
use ApiSutra\Attributes\Request\AuthScope;
use ApiSutra\Attributes\Request\OperationDescriptor;
use ApiSutra\Attributes\Response\ContinuationResult;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Enums\Http\HttpMethod;

final readonly class RequestSpec
{
    public function __construct(
        public ?HttpMethod $method,
        public ?string $endpoint,
        public ?string $responseType,
        public ?Returns $returns,
        public ?ContinuationResult $continuationResult,
        public ?OperationDescriptor $operationDescriptor,
        public ?Cache $cache,
        public ?Retry $retry,
        public ?Timeout $timeout,
        public ?Idempotent $idempotent,
        public ?Execution $execution,
        public ?Pagination $pagination,
        public ?RateLimit $rateLimit,
        public ?AuthScope $authScope,
        public bool $hasNoAuth,
        public bool $skipCredentialsEnrichment,
        public bool $hasDownload,
        public bool $hasRawResponse = false,
        public ?Cooldown $cooldown = null,
    ) {
    }
}
