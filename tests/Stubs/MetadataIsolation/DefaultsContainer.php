<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

use ApiSutra\DataTransfer\AbstractPaginationContainerDto;

final readonly class DefaultsContainer extends AbstractPaginationContainerDto
{
    public function __construct(
        array|object|null $items = null,
        public MutableCounter $state = new MutableCounter(),
    ) {
        parent::__construct($items);
    }

    public function withItems(array|object $items): static
    {
        return new static($items, $this->state);
    }
}
