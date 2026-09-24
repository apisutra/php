<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Core;

use ApiSutra\VO\Http\TransportOptions;

/** Транспорт проверяет поддержку и применяет effective() непосредственно перед HTTP. */
interface TimeoutAwareTransportInterface extends TransportInterface
{
    public function assertSupportsTimeouts(TransportOptions $options): void;
}
