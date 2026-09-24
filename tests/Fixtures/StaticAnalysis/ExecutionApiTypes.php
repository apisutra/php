<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Fixtures\StaticAnalysis;

use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Core\AbstractClient;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Request\RequestExecution;
use ApiSutra\Result\PoolSummary;
use ApiSutra\Result\ResultHandle;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Promise\PromiseInterface;
use RuntimeException;
use function PHPStan\Testing\assertType;

/** Проверяется PHPStan; функция не отправляет запросы при запуске тестов. */
function executionApiTypes(
    AbstractClient $client,
    ClientInterface $contract,
    AbstractRequest $request,
    RequestExecution $execution,
    PipelineContext $parent,
    PromiseInterface $foreign,
    bool $unknown,
): void {
    assertType('ApiSutra\Result\ResultHandle', $client->sendAsync($request)->wait());
    assertType('ApiSutra\Result\ResultHandle', $contract->sendAsync($request)->wait());
    assertType('ApiSutra\Result\ResultHandle', $request->sendAsync()->wait());
    assertType('ApiSutra\Result\ResultHandle', $execution->sendAsync()->wait());
    assertType('ApiSutra\Result\ResultHandle', $client->sendInContextAsync($request, $parent, RequestRole::Nested)->wait());
    assertType('ApiSutra\Result\ResolvedResultInterface', $request->resolvedAsync()->wait());
    assertType('ApiSutra\Result\ResolvedResultInterface', $execution->resolvedAsync()->wait());
    assertType('ApiSutra\Result\PoolResult', $client->pool([$request])->sendAsync()->wait());
    assertType('ApiSutra\Result\PoolSummary', $client->pool([$request])->consumeAsync()->wait());
    assertType('ApiSutra\Result\BatchResult', $client->batch([$request])->sendAsync()->wait());
    assertType('null', $client->sendAsync($request)->wait(false));
    assertType('ApiSutra\Result\ResultHandle|null', $client->sendAsync($request)->wait($unknown));
    assertType('ApiSutra\Result\ResultHandle', $client->sendAsync($request)->then()->wait());
    assertType('ApiSutra\Result\ResultHandle', $client->sendAsync($request)->then(null, null)->wait());
    assertType('string', $client->sendAsync($request)->then(static fn (ResultHandle $result): string => $result::class)->wait());
    assertType('ApiSutra\Result\ResultHandle|string', $client->sendAsync($request)->otherwise(static fn (mixed $reason): string => 'fallback')->wait());
    assertType('ApiSutra\Result\PoolSummary', $client->sendAsync($request)->then(fn () => $client->pool([$request])->consumeAsync())->wait());
    assertType('ApiSutra\Result\PoolSummary|ApiSutra\Result\ResultHandle', $client->sendAsync($request)->otherwise(fn () => $client->pool([$request])->consumeAsync())->wait());
    assertType('ApiSutra\Result\PoolSummary|ApiSutra\Result\ResultHandle', $client->sendAsync($request)->then(null, fn () => $client->pool([$request])->consumeAsync())->wait());
    assertType('mixed', $client->sendAsync($request)->then(static fn () => $foreign)->wait());
    assertType('mixed', $client->sendAsync($request)->otherwise(static fn () => $foreign)->wait());
    assertType('ApiSutra\Result\PoolSummary|ApiSutra\Result\ResultHandle', $client->sendAsync($request)->then(fn (ResultHandle $handle) => $unknown ? $handle : $client->pool([$request])->consumeAsync())->wait());
    assertType('int', $client->sendAsync($request)->then(fn () => $client->pool([$request])->consumeAsync())->then(static fn (PoolSummary $summary): int => $summary->total)->wait());
    assertType('ApiSutra\Result\ResultHandle', $client->sendAsync($request)->otherwise(static fn () => throw new RuntimeException())->wait());
    assertType('mixed', $client->sendAsync($request)->wait()->dataOrFail());
    // Расширяемый класс теоретически может также реализовать сторонний PromiseInterface.
    assertType('mixed', $client->sendAsync($request)->then(static fn (ResultHandle $handle) => $handle->raw())->wait());
    $client->sendAsync($request)->then(static function ($handle) {
        assertType('ApiSutra\Result\ResultHandle', $handle);
        return $handle->raw();
    });
}
