<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Retry;

use ApiSutra\Contracts\Interfaces\Core\ConcurrentTransportInterface;
use ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface;
use ApiSutra\Contracts\Interfaces\Core\TimeoutAwareTransportInterface;
use ApiSutra\Http\RequestDestination;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Contracts\Interfaces\Core\FileStreamingInterface;
use ApiSutra\Transport\TransportExecution;
use ApiSutra\Transport\TransportCapabilities;
use ApiSutra\Http\DestinationGuard;
use ApiSutra\Files\FileTransferGuard;
use ApiSutra\VO\Files\FileTransferOptions;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Http\TransportOptions;
use Closure;
use GuzzleHttp\Promise\PromiseInterface;

final class ProbeTransportDecorator implements ConcurrentTransportInterface, TimeoutAwareTransportInterface, DestinationAwareInterface, FileStreamingInterface
{
    /** @var list<string> */
    public array $entries = [];

    /** @param Closure(string, PreparedRequest): void|null $onSend @param Closure(): void|null $onCheck @param Closure(PreparedRequest): PreparedRequest|null $map */
    public function __construct(public TransportInterface $inner, private ?Closure $onSend = null, private ?Closure $onCheck = null, private ?Closure $map = null)
    {
    }

    public function assertSupportsConcurrency(): void
    {
        TransportExecution::assertConcurrent($this->inner);
    }
    public function assertSupportsDestination(RequestDestination $destination): void
    {
        DestinationGuard::checkCapability($this->inner, $destination);
    }
    public function assertSupportsFileTransfer(FileTransferOptions $options): void
    {
        FileTransferGuard::checkCapability($this->inner, $options);
    }
    public function assertSupportsTimeouts(TransportOptions $options): void
    {
        TransportCapabilities::check($this->inner, $options);
        ($this->onCheck)?->__invoke();
    }
    public function send(PreparedRequest $request): ProviderResponse
    {
        $this->entries[] = 'sync';
        ($this->onSend)?->__invoke('sync', $request);
        $request = $this->map === null ? $request : ($this->map)($request);
        return $this->inner->send($request);
    }
    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        $this->entries[] = 'async';
        ($this->onSend)?->__invoke('async', $request);
        $request = $this->map === null ? $request : ($this->map)($request);
        return $this->inner->sendAsync($request);
    }
}
