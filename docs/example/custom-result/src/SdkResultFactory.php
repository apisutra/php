<?php

declare(strict_types=1);

namespace Example\CustomResult;

use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResolvedResultFactoryInterface;
use Override;

final readonly class SdkResultFactory implements ResolvedResultFactoryInterface
{
    public function __construct(private ResolvedResultFactoryInterface $defaults)
    {
    }

    #[Override]
    public function make(ExecutionResult $result): SdkResult
    {
        return new SdkResult($this->defaults->make($result));
    }
}
