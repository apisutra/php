<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Execution;

use ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResultMetaExtractorInterface;

final class CountingMetaExtractor implements ResultMetaExtractorInterface
{
    /** @var list<ExecutionResult> */
    public array $results = [];

    public function extract(ExecutionResult $result): ?ResultMeta
    {
        $this->results[] = $result;
        return null;
    }
}
