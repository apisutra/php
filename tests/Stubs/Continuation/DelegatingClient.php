<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Continuation;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Continuation\ContinuationService;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Response\ClientResponse;
use ApiSutra\Result\ResolvedResultInterface;
use ApiSutra\Result\ResultHandle;
use ApiSutra\Contracts\Interfaces\Execution\ResultPromiseInterface;
use ApiSutra\Serialization\Hydrator;

final readonly class DelegatingClient implements ClientInterface
{
    private ContinuationService $service;

    public function __construct(private ClientInterface $client, Hydrator $hydrator)
    {
        $this->service = new ContinuationService($this, $hydrator);
    }

    public function execution(): ClientExecutorInterface
    {
        return $this->client->execution();
    }

    public function send(RequestInterface $request): ResultHandle
    {
        return $this->client->send($request);
    }

    public function sendAsync(RequestInterface $request): ResultPromiseInterface
    {
        return $this->client->sendAsync($request);
    }

    public function response(ResolvedResultInterface $result): ClientResponse
    {
        return $this->client->response($result);
    }

    public function getConfig(): ClientConfig
    {
        return $this->client->getConfig();
    }

    public function continuation(): ContinuationService
    {
        return $this->service;
    }
}
