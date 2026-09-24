<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Tracing;

use ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResultMetaExtractorInterface;
use ApiSutra\Tests\Support\VirtualClock;
use RuntimeException;

final readonly class FinalMetaExtractor implements ResultMetaExtractorInterface
{
    public function __construct(private ?VirtualClock $clock = null)
    {
    }

    public function extract(ExecutionResult $result): ?ResultMeta
    {
        if ($this->clock === null) {
            throw new RuntimeException('Metadata failed');
        }
        $this->clock->advance(20);
        return null;
    }
}
