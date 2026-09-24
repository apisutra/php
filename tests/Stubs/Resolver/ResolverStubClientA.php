<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Resolver;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Continuation\ContinuationService;
use RuntimeException;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\Result\ResultHandle;
use ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface;
use ApiSutra\Response\ClientResponse;

final class ResolverStubClientA implements ClientInterface
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
        throw new RuntimeException('Отправка не используется в тестах резолвера.');
    }

    public function sendAsync(RequestInterface $request): ResultPromiseInterface
    {
        throw new RuntimeException('Отправка не используется в тестах резолвера.');
    }

    public function response(ResolvedResultInterface $result): ClientResponse
    {
        throw new RuntimeException('Ответ не используется в тестах резолвера.');
    }

    public function getConfig(): ClientConfig
    {
        return $this->config;
    }

    public function continuation(): ContinuationService
    {
        throw new RuntimeException('Continuation не используется в тестах резолвера.');
    }
}
