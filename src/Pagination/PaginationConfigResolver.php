<?php

declare(strict_types=1);

namespace ApiSutra\Pagination;

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Core\AbstractRequest;

final readonly class PaginationConfigResolver
{
    public function __construct(
        private ?ClientConfig $config,
    ) {
    }

    public function resolve(AbstractRequest $request): PaginationConfig
    {
        $defaults = $this->resolveBaseDefaults();
        $attribute = $request->getPaginationAttribute();
        if ($attribute === null) {
            return $defaults;
        }

        return new PaginationConfig(
            pageParam: $attribute->pageParam ?? $defaults->pageParam,
            limitParam: $attribute->limitParam ?? $defaults->limitParam,
            cursorParam: $attribute->cursorParam ?? $defaults->cursorParam,
            metaPath: $attribute->metaPath ?? $defaults->metaPath,
            itemsPath: $attribute->itemsPath ?? $defaults->itemsPath,
            offsetBased: $attribute->offsetBased ?? $defaults->offsetBased,
            metaResolver: $attribute->metaResolver ?? $defaults->metaResolver,
            itemsType: $attribute->itemsType ?? $defaults->itemsType,
            itemsCollection: $attribute->itemsCollection ?? $defaults->itemsCollection,
            itemsCollectionFactory: $attribute->itemsCollectionFactory ?? $defaults->itemsCollectionFactory,
            maxPages: $attribute->maxPages ?? $defaults->maxPages,
        );
    }

    private function resolveBaseDefaults(): PaginationConfig
    {
        if ($this->config === null) {
            return new PaginationConfig();
        }

        return $this->config->paginationConfig ?? new PaginationConfig();
    }
}
