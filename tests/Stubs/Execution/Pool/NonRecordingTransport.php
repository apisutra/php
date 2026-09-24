<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Execution\Pool;

use ApiSutra\Contracts\Interfaces\Core\ConcurrentTransportInterface;
use ApiSutra\Contracts\Interfaces\Core\TimeoutAwareTransportInterface;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Http\TransportOptions;
use GuzzleHttp\Promise\FulfilledPromise;
use GuzzleHttp\Promise\PromiseInterface;
use Override;

/** Транспорт для замера удержания SDK, без собственной истории и сетевого ожидания. */
final class NonRecordingTransport implements ConcurrentTransportInterface, TimeoutAwareTransportInterface
{
    public int $sent = 0;

    public function __construct(private readonly bool $failed = false, private readonly int $payloadBytes = 16384)
    {
    }

    #[Override]
    public function send(PreparedRequest $request): ProviderResponse
    {
        $payload = str_pad((string) ++$this->sent, $this->payloadBytes, 'x');
        return new ProviderResponse($this->failed ? 400 : 200, ['Content-Type' => ['application/json']], json_encode(['payload' => $payload], JSON_THROW_ON_ERROR), $request, 0);
    }

    #[Override]
    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        return new FulfilledPromise($this->send($request));
    }

    #[Override]
    public function assertSupportsConcurrency(): void
    {
    }

    #[Override]
    public function assertSupportsTimeouts(TransportOptions $options): void
    {
    }
}
