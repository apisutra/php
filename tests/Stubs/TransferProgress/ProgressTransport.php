<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\TransferProgress;

use ApiSutra\Contracts\Interfaces\Core\ConcurrentTransportInterface;
use ApiSutra\Contracts\Interfaces\Core\TimeoutAwareTransportInterface;
use ApiSutra\Contracts\Interfaces\Core\TransferProgressInterface;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Testing\MockResponse;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Http\TransportOptions;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;

/** Управляемые счётчики: проверяем SDK без сетевого шума. */
final class ProgressTransport implements ConcurrentTransportInterface, TimeoutAwareTransportInterface, TransferProgressInterface
{
    public readonly MockTransport $inner;
    /** @var list<PreparedRequest> */
    public array $requests = [];

    public function __construct()
    {
        $this->inner = new MockTransport();
        $this->inner->fake(['*' => MockResponse::success(['ok' => true])]);
    }

    public function assertSupportsConcurrency(): void
    {
    }
    public function assertSupportsTimeouts(TransportOptions $options): void
    {
    }
    public function assertSupportsTransferProgress(): void
    {
    }

    public function send(PreparedRequest $request): ProviderResponse
    {
        $this->requests[] = $request;
        $progress = $request->transportOptions?->effective()->transferProgress;
        $progress?->__invoke(0, 0, 0, 0);
        $progress?->__invoke(0, 0, 0, 0);
        $progress?->__invoke(10, 0, 0, 0);
        $progress?->__invoke(10, 10, 0, 0);
        return $this->inner->send($request);
    }

    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        return new FulfilledPromise($this->send($request));
    }
}
