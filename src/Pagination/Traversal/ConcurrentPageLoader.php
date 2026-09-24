<?php

declare(strict_types=1);

namespace ApiSutra\Pagination\Traversal;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Execution\Async\AsyncRuntime;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Execution\ConcurrentExecution;
use ApiSutra\Result\ExecutionResult;
use Closure;
use Generator;
use GuzzleHttp\Promise\PromiseInterface;
use WeakMap;

/** @internal Bootstrap и скользящее окно; правила выбора страниц принадлежат progress. */
final class ConcurrentPageLoader
{
    /**
     * @param Generator<int, RequestInterface> $pages Номер страницы → снимок запроса.
     * @param Closure(RequestInterface): PromiseInterface $dispatch
     * @param Closure(int, ExecutionResult): void $record
     */
    public function load(Generator $pages, int $concurrency, Closure $dispatch, Closure $record): void
    {
        $runtime = AsyncTask::current()?->runtime;
        if ($runtime === null) {
            $runtime = new AsyncRuntime();
            $runtime->promises->await($runtime->start(fn () => $this->load($pages, $concurrency, $dispatch, $record)));
            return;
        }
        $pages->rewind();
        if (!$pages->valid()) {
            return;
        }
        // Даже у sync-терминала bootstrap проверяет async capability до auth и HTTP.
        $result = $runtime->promises->await($dispatch($pages->current()));
        $record($pages->key(), $result);
        unset($result);

        $positions = new WeakMap();
        $remaining = (static function () use ($pages, $positions): Generator {
            $pages->next();
            while ($pages->valid()) {
                $request = $pages->current();
                $positions[$request] = $pages->key();
                yield $request;
                $pages->next();
            }
        })();
        $outcome = ConcurrentExecution::run(
            $remaining,
            $concurrency,
            $dispatch,
            false,
            static function (ExecutionResult $result, RequestInterface $request) use ($record, $positions): void {
                $page = $positions[$request];
                unset($positions[$request]);
                $record($page, $result);
            },
        );
        if ($outcome->failure !== null) {
            throw $outcome->failure;
        }
    }
}
