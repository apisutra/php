<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Core;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Continuation\ContinuationService;
use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface;
use ApiSutra\Response\ClientResponse;
use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\Result\ResultHandle;

interface ClientInterface
{
    public function execution(): ClientExecutorInterface;

    /**
     * Выполнить запрос синхронно
     */
    public function send(RequestInterface $request): ResultHandle;

    /**
     * Выполнить запрос асинхронно
     * @return ResultPromiseInterface<ResultHandle>
     */
    public function sendAsync(RequestInterface $request): ResultPromiseInterface;

    /**
     * Сформировать клиентский ответ
     */
    public function response(ResolvedResultInterface $result): ClientResponse;

    /**
     * Получить конфигурацию
     */
    public function getConfig(): ClientConfig;

    /**
     * Получить сервис continuation orchestration.
     */
    public function continuation(): ContinuationService;
}
