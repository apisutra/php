<?php

declare(strict_types=1);

namespace ApiSutra\Attributes\Behavior;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class Pagination
{
    public function __construct(
        public ?string $pageParam = null,
        public ?string $limitParam = null,
        public ?string $cursorParam = null,
        public ?string $metaPath = null,
        public ?string $itemsPath = null,
        public ?bool $offsetBased = null,
        public ?string $metaResolver = null,
        public ?string $itemsType = null,
        public ?string $itemsCollection = null,
        public ?string $itemsCollectionFactory = null,
        public ?int $maxPages = null,
    ) {
    }
}
