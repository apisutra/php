<?php

declare(strict_types=1);

namespace ApiSutra\Catalog;

use ApiSutra\Contracts\Interfaces\Catalog\ProviderCatalogMetaInterface;
use DateTimeImmutable;

final readonly class ProviderCatalogMeta implements ProviderCatalogMetaInterface
{
    public function __construct(
        private DateTimeImmutable $generatedAt,
        private ?string $source = null,
        private ?string $sourceVersion = null,
    ) {
    }

    #[\Override]
    public function generatedAt(): DateTimeImmutable
    {
        return $this->generatedAt;
    }

    #[\Override]
    public function source(): ?string
    {
        return $this->source;
    }

    #[\Override]
    public function sourceVersion(): ?string
    {
        return $this->sourceVersion;
    }
}
