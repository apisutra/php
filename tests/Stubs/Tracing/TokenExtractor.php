<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Tracing;

use ApiSutra\Result\ContinuationTokenExtractorInterface;
use ApiSutra\Result\ExecutionResult;

final class TokenExtractor implements ContinuationTokenExtractorInterface
{
    public function extract(ExecutionResult $result): ?string
    {
        $value = $result->response?->json('operationToken');
        return is_string($value) ? $value : null;
    }
}
