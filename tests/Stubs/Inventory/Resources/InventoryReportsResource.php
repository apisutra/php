<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Inventory\Resources;

use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Core\AbstractResource;
use ApiSutra\Tests\Stubs\Inventory\Requests\InventoryV1ReportRequest;
use ApiSutra\Tests\Stubs\Inventory\Requests\InventoryV2ReportRequest;
use ApiSutra\Versioning\VersionedResourceTrait;

final class InventoryReportsResource extends AbstractResource
{
    use VersionedResourceTrait;

    public function __construct(
        ClientInterface $client,
        private readonly string $version = '',
    ) {
        parent::__construct($client);
    }

    public function v1(): self
    {
        return $this->recreateWithVersion('v1');
    }

    public function v2(): self
    {
        return $this->recreateWithVersion('v2');
    }

    public function useVersion(string $version): self
    {
        return $this->recreateWithVersion($version);
    }

    public function fetch(string $reportId): InventoryV1ReportRequest|InventoryV2ReportRequest
    {
        /** @var InventoryV1ReportRequest|InventoryV2ReportRequest $request */
        $request = $this->requestByVersion([
            'v1' => InventoryV1ReportRequest::class,
            'v2' => InventoryV2ReportRequest::class,
        ], $reportId);

        return $request;
    }

    #[\Override]
    protected function currentVersionKey(): string
    {
        return $this->version;
    }

    #[\Override]
    protected function recreateWithVersion(string $version): static
    {
        return new self($this->client, $version);
    }
}
