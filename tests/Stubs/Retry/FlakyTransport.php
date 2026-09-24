<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Retry;

use ApiSutra\Contracts\Interfaces\Core\TimeoutAwareTransportInterface;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Http\TransportOptions;
use GuzzleHttp\Promise\PromiseInterface;
use RuntimeException;

final class FlakyTransport implements TimeoutAwareTransportInterface
{
    public function assertSupportsTimeouts(TransportOptions $options): void {}

    public int $calls = 0;

    public function send(PreparedRequest $request): ProviderResponse
    {
        $this->calls++;
        if ($this->calls === 1) {
            throw new FlakyTransportException('fail');
        }

        return new ProviderResponse(
            status: 200,
            headers: ['Content-Type' => ['application/json']],
            body: '{}',
            request: $request,
            duration: 0,
        );
    }

    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        throw new RuntimeException('Not implemented for tests.');
    }
}
