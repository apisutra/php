<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Request;

use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResolvedResultFactoryInterface;
use ApiSutra\Result\ResolvedResultInterface;

final class TestResolvedResultFactory implements ResolvedResultFactoryInterface
{
    public function make(ExecutionResult $result): ResolvedResultInterface
    {
        return new TestResolvedResult($result);
    }
}
