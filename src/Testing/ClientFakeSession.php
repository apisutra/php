<?php

declare(strict_types=1);

namespace ApiSutra\Testing;

use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Testing\UnmockedRequestException;
use ApiSutra\Localization\Message;
use ApiSutra\Transport\MockTransport;
use Closure;

/** Область fake: новая история, сохранённое нарушение и явное восстановление. */
final class ClientFakeSession
{
    private ?MockTransport $transport = null;
    private ?string $unmocked = null;
    private bool $closed = false;

    /** @param Closure(TransportInterface): void $replace
     * @param Closure(): void $restore
     */
    public function __construct(private readonly Closure $replace, private readonly Closure $restore)
    {
    }

    /** @param array<string, mixed> $responses */
    public function fake(array $responses): self
    {
        $this->assertOpen();
        $this->rememberViolation();
        $transport = new MockTransport();
        $transport->fake($responses);
        $transport->preventStrayRequests();
        ($this->replace)($transport);
        $this->transport = $transport;
        return $this;
    }

    public function assertSent(string $requestClass, ?callable $callback = null, ?int $times = null): void
    {
        $this->mock()->assertSent($requestClass, $callback, $times);
    }

    public function assertNotSent(string $requestClass): void
    {
        $this->mock()->assertNotSent($requestClass);
    }

    public function assertNothingSent(): void
    {
        $this->mock()->assertNothingSent();
    }

    public function verify(): void
    {
        $this->rememberViolation();
        if ($this->unmocked !== null) {
            throw new UnmockedRequestException(new Message('testing.unmocked_in_session', ['requestClass' => $this->unmocked]));
        }
    }

    public function close(): void
    {
        if ($this->closed) {
            return;
        }
        $this->rememberViolation();
        ($this->restore)();
        $this->closed = true;
        $this->transport = null;
    }

    private function rememberViolation(): void
    {
        $this->unmocked ??= $this->transport?->unmockedRequest();
    }

    private function mock(): MockTransport
    {
        $this->assertOpen();
        return $this->transport ?? throw new ConfigurationException(new Message('testing.mock_transport_required'));
    }

    private function assertOpen(): void
    {
        if ($this->closed) {
            throw new ConfigurationException(new Message('testing.fake_session_closed'));
        }
    }
}
