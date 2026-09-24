<?php

declare(strict_types=1);

namespace Acme\Discovery;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Core\AbstractClient;

final class FlowClient extends AbstractClient
{
    public function __construct(ClientConfig $config, TransportInterface $transport)
    {
        parent::__construct($config, $transport);
    }
}
