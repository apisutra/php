<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Contracts\Interfaces\Core\TimeoutAwareTransportInterface;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Http\TransportOptions;
use GuzzleHttp\Promise\PromiseInterface;

final class FakeTransport implements TimeoutAwareTransportInterface
{
    public function assertSupportsTimeouts(TransportOptions $options): void {}

    public int $sendCalls = 0;
    public ?ProviderResponse $nextResponse = null;

    public function send(PreparedRequest $request): ProviderResponse
    {
        $this->sendCalls++;

        if ($this->nextResponse instanceof ProviderResponse) {
            return $this->nextResponse;
        }

        return new ProviderResponse(
            status: 200,
            headers: [],
            body: '',
            request: $request,
            duration: 0,
        );
    }

    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        throw new \RuntimeException('Not implemented for tests.');
    }
}
