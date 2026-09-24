<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Execution;

use ApiSutra\Result\ContinuationTokenExtractorInterface;
use ApiSutra\Result\ExecutionResult;

final class TokenExtractor implements ContinuationTokenExtractorInterface
{
    public function extract(ExecutionResult $result): ?string
    {
        return $result->response?->json('operationToken');
    }
}
