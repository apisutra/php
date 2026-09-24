<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Contracts\Interfaces\Timing\ClockInterface;
use ApiSutra\Contracts\Interfaces\Timing\SleeperInterface;
use ApiSutra\Core\AbstractClient;
use ApiSutra\Timing\SystemClock;

final class TestClient extends AbstractClient
{
    public function __construct(ClientConfig $config, TransportInterface $transport, ?SleeperInterface $sleeper = null, ClockInterface $clock = new SystemClock())
    {
        parent::__construct($config, $transport, sleeper: $sleeper, clock: $clock);
    }
}
