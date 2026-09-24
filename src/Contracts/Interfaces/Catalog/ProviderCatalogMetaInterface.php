<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Catalog;

use DateTimeImmutable;

interface ProviderCatalogMetaInterface
{
    public function generatedAt(): DateTimeImmutable;

    public function source(): ?string;

    public function sourceVersion(): ?string;
}
