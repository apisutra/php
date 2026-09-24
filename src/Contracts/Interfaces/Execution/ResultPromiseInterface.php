<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Execution;

use GuzzleHttp\Promise\PromiseInterface;

/**
 * Промис готового результата; тип значения сохраняется при ожидании и преобразовании.
 *
 * @template T
 */
interface ResultPromiseInterface extends PromiseInterface
{
    /** @return ($unwrap is true ? T : null) */
    public function wait(bool $unwrap = true): mixed;

    /**
     * @template TFulfilled = never
     * @template TRejected = never
     * @param (callable(T): TFulfilled)|null $onFulfilled
     * @param (callable(mixed): TRejected)|null $onRejected
     * @return ResultPromiseInterface<
     *   ($onFulfilled is null ? T :
     *     (TFulfilled is ResultPromiseInterface<mixed> ? template-type<TFulfilled, ResultPromiseInterface, 'T'> :
     *       (TFulfilled is PromiseInterface ? mixed : TFulfilled)))
     *   | ($onRejected is null ? never :
     *     (TRejected is ResultPromiseInterface<mixed> ? template-type<TRejected, ResultPromiseInterface, 'T'> :
     *       (TRejected is PromiseInterface ? mixed : TRejected)))
     * >
     */
    public function then(?callable $onFulfilled = null, ?callable $onRejected = null): ResultPromiseInterface;

    /**
     * @template TNext
     * @param callable(mixed): TNext $onRejected
     * @return ResultPromiseInterface<T
     *   | (TNext is ResultPromiseInterface<mixed> ? template-type<TNext, ResultPromiseInterface, 'T'> :
     *       (TNext is PromiseInterface ? mixed : TNext))
     * >
     */
    public function otherwise(callable $onRejected): ResultPromiseInterface;
}
