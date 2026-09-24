<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Core;

/** sendAsync продвигается в цикле SDK без блокирующего ожидания сети. */
interface ConcurrentTransportInterface extends TransportInterface
{
    public function assertSupportsConcurrency(): void;
}
