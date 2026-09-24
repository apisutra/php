<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\CatalogMega;

use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Core\MultiServiceClientInterface;
use ApiSutra\Contracts\Interfaces\Inventory\ResponseDtoCatalogProviderInterface;
use ApiSutra\Traits\ProvidesMultiServiceResponseDtoCatalogTrait;

final class CatalogMegaClient implements MultiServiceClientInterface, ResponseDtoCatalogProviderInterface
{
    use ProvidesMultiServiceResponseDtoCatalogTrait;

    /**
     * @param array<int, ClientInterface> $services
     */
    public function __construct(
        private readonly array $services,
    ) {}

    /**
     * @return array<int, ClientInterface>
     */
    #[\Override]
    public function services(): array
    {
        return $this->services;
    }
}
