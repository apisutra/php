<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Result\ExecutionResult;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use PHPUnit\Framework\Assert;
use Throwable;

final class ExecutionDelivery
{
    /** @param callable(): ExecutionResult $send */
    public static function result(callable $send, bool $throws, RecordingFactory $factory): ExecutionResult
    {
        try {
            $result = $send();
        } catch (Throwable $exception) {
            Assert::assertTrue($throws);
            Assert::assertCount(1, $factory->results);
            $result = $factory->results[0];
            Assert::assertSame($result->exception, $exception);
            Assert::assertTrue($result->isFailed());
            return $result;
        }
        Assert::assertFalse($throws);
        Assert::assertCount(0, $factory->results);
        return $result;
    }
}
