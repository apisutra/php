<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Localization;

use ApiSutra\Contracts\Interfaces\Core\TimeoutAwareTransportInterface;
use ApiSutra\VO\Http\TransportOptions;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use Fiber;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\RejectedPromise;
use Throwable;

final readonly class ThrowingTransport implements TimeoutAwareTransportInterface
{
    public function __construct(private Throwable $exception, private bool $suspend = false)
    {
    }

    public function assertSupportsTimeouts(TransportOptions $options): void
    {
    }

    public function send(PreparedRequest $request): ProviderResponse
    {
        if ($this->suspend) {
            Fiber::suspend();
        }
        throw $this->exception;
    }

    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        return new RejectedPromise($this->exception);
    }
}
