<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Core\AbstractClient;
use ApiSutra\Hooks\HookRegistry;

final class HookedClient extends AbstractClient
{
    public function __construct(ClientConfig $config, TransportInterface $transport, HookRegistry $hooks)
    {
        parent::__construct($config, $transport, $hooks);
    }

    public function hooks(): HookRegistry
    {
        return parent::hooks();
    }
}
