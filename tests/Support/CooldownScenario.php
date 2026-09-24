<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Support;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Testing\MockResponse;

final readonly class CooldownScenario
{
    public VirtualClock $clock;
    public MockTransport $transport;
    public TestClient $client;

    public function __construct(?ClientConfig $config = null, int $seconds = 30)
    {
        $this->clock = new VirtualClock();
        $this->transport = new MockTransport();
        $this->transport->preventStrayRequests();
        $this->transport->fake(['*' => MockResponse::sequence([MockResponse::rateLimited($seconds), MockResponse::success()])]);
        $this->client = new TestClient($config ?? new ClientConfig(baseUrl: 'https://fixture.test'), $this->transport, $this->clock, $this->clock);
    }
}
