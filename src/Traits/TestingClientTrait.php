<?php

declare(strict_types=1);

namespace ApiSutra\Traits;

use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Testing\ClientFakeSession;
use ApiSutra\Testing\Fixture;
use ApiSutra\Testing\MockConfig;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Transport\RecordingTransport;

/**
 * Тестовые методы клиента.
 */
trait TestingClientTrait
{
    private bool $fakeSessionOpen = false;

    /** Обратимая подмена для интеграций с жизненным циклом теста. */
    public function beginFakeSession(): ClientFakeSession
    {
        $this->assertTestingTransportMutable();
        if ($this->fakeSessionOpen) {
            throw new ConfigurationException(new Message('testing.fake_session_open'));
        }
        $original = $this->transport;
        $this->fakeSessionOpen = true;
        return new ClientFakeSession(
            function (TransportInterface $transport): void {
                $this->assertTestingTransportMutable();
                $this->transport = $transport;
                $this->rebuildPipeline();
            },
            function () use ($original): void {
                $this->assertTestingTransportMutable();
                $this->transport = $original;
                $this->rebuildPipeline();
                $this->fakeSessionOpen = false;
            },
        );
    }

    public function fake(array $responses): static
    {
        $this->assertDirectTestingChange();
        $transport = $this->ensureMockTransport();
        $transport->fake($responses);
        $this->rebuildPipeline();

        return $this;
    }

    public function preventStrayRequests(): void
    {
        $this->requireMockTransport()->preventStrayRequests();
    }

    /**
     * Записать ответы в фикстуры
     * @param array<string, Fixture> $fixtures
     */
    public function record(string $path, array $fixtures = []): static
    {
        $this->assertDirectTestingChange();
        $this->transport = new RecordingTransport($this->transport, $path, $fixtures, $this->config->redaction);
        MockConfig::setFixturePath($path);
        $this->rebuildPipeline();

        return $this;
    }

    /**
     * Воспроизвести ответы из фикстур
     */
    public function playback(string $path): static
    {
        $this->assertDirectTestingChange();
        $transport = new MockTransport();
        $transport->loadFixtures($path);
        $this->transport = $transport;
        MockConfig::setFixturePath($path);
        $this->rebuildPipeline();

        return $this;
    }

    public function assertSent(string $requestClass, ?callable $callback = null, ?int $times = null): void
    {
        $this->requireMockTransport()->assertSent($requestClass, $callback, $times);
    }

    public function assertNotSent(string $requestClass): void
    {
        $this->requireMockTransport()->assertNotSent($requestClass);
    }

    public function assertNothingSent(): void
    {
        $this->requireMockTransport()->assertNothingSent();
    }

    private function ensureMockTransport(): MockTransport
    {
        if (!$this->transport instanceof MockTransport) {
            $this->transport = new MockTransport();
        }

        return $this->transport;
    }

    private function assertDirectTestingChange(): void
    {
        $this->assertTestingTransportMutable();
        if ($this->fakeSessionOpen) {
            throw new ConfigurationException(new Message('testing.fake_session_open'));
        }
    }

    private function assertTestingTransportMutable(): void
    {
        if ($this->executor->isActive()) {
            throw new ConfigurationException(new Message('testing.transport_in_use'));
        }
    }

    private function requireMockTransport(): MockTransport
    {
        if (!$this->transport instanceof MockTransport) {
            throw new ConfigurationException(
                new Message('testing.mock_transport_required'),
                localization: $this->config->localization,
            );
        }

        return $this->transport;
    }
}
