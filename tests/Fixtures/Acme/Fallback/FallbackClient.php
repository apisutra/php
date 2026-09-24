<?php

declare(strict_types=1);

namespace Acme\Fallback;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Continuation\ContinuationService;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Response\ClientResponse;
use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\Result\ResultHandle;
use ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface;
use RuntimeException;

final class FallbackClient implements ClientInterface
{
    public function __construct(
        private ClientConfig $config,
    ) {}

    public function execution(): ClientExecutorInterface
    {
        throw new RuntimeException('Исполнение не используется в тестах реестра.');
    }

    public function send(RequestInterface $request): ResultHandle
    {
        throw new RuntimeException('Отправка не используется в тестах discovery.');
    }

    public function sendAsync(RequestInterface $request): ResultPromiseInterface
    {
        throw new RuntimeException('Отправка не используется в тестах discovery.');
    }

    public function response(ResolvedResultInterface $result): ClientResponse
    {
        throw new RuntimeException('Ответ не используется в тестах discovery.');
    }

    public function getConfig(): ClientConfig
    {
        return $this->config;
    }

    public function continuation(): ContinuationService
    {
        throw new RuntimeException('Continuation не используется в тестах discovery.');
    }
}
