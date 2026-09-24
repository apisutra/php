<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Inventory;

use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Core\AbstractResource;
use ApiSutra\Tests\Stubs\Inventory\Resources\InventoryOrdersResource;
use ApiSutra\Tests\Stubs\Inventory\Resources\InventoryReportsResource;

final class InventoryVersionRouter extends AbstractResource
{
    public function __construct(
        ClientInterface $client,
        private readonly string $version,
    ) {
        parent::__construct($client);
    }

    public function v1(): self
    {
        return new self($this->client, 'v1');
    }

    public function v2(): self
    {
        return new self($this->client, 'v2');
    }

    public function useVersion(string $version): self
    {
        return new self($this->client, $version);
    }

    public function orders(): InventoryOrdersResource
    {
        return new InventoryOrdersResource($this->client);
    }

    public function reports(): InventoryReportsResource
    {
        return new InventoryReportsResource($this->client, $this->version);
    }
}
