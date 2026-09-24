<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Core;

use ApiSutra\Http\RequestDestination;

/**
 * Гарантия проверки назначения перед I/O: без redirects, унаследованных credentials
 * и изменения готового request target. Применима к транспорту, PSR-адаптеру и retry handler.
 */
interface DestinationAwareInterface
{
    public function assertSupportsDestination(RequestDestination $destination): void;
}
