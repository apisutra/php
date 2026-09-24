<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Catalog;

interface ProviderCatalogInterface
{
    public function key(): string;

    public function meta(): ProviderCatalogMetaInterface;
}
