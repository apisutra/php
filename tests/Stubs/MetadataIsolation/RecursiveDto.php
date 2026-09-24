<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

use ApiSutra\DataTransfer\AbstractDto;

final readonly class RecursiveDto extends AbstractDto
{
    public function __construct(
        public ?RecursiveDto $child = null,
        public CreatedValue $state = new CreatedValue(),
    ) {
    }
}
