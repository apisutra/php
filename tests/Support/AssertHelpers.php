<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Contracts\Interfaces\Core\ResultInterface;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Request\PaginationOptions;
use ApiSutra\Request\RequestOptions;
use ApiSutra\VO\Pipeline\PipelineContext;
use PHPUnit\Framework\Assert;

trait AssertHelpers
{
    protected function assertContextHasOptions(PipelineContext $context, ?RequestOptions $expected = null): RequestOptions
    {
        Assert::assertNotNull($context->options, 'Ожидались опции запроса в контексте');

        if ($expected !== null) {
            Assert::assertEquals($expected, $context->options);
        }

        return $context->options;
    }

    protected function assertContextHasPaginationOptions(
        PipelineContext $context,
        ?PaginationOptions $expected = null,
    ): PaginationOptions {
        Assert::assertNotNull($context->paginationOptions, 'Ожидались опции пагинации в контексте');

        if ($expected !== null) {
            Assert::assertEquals($expected, $context->paginationOptions);
        }

        return $context->paginationOptions;
    }

    protected function assertResultStatus(ResultInterface $result, ResultStatus $expected): void
    {
        Assert::assertSame($expected, $result->status);
    }

    protected function assertCacheKeyContains(string $cacheKey, string $needle): void
    {
        Assert::assertStringContainsString($needle, $cacheKey);
    }
}
