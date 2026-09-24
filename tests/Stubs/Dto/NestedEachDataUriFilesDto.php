<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Casts\DataUriBase64FileCast;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Collections\Base64FileCollection;

final readonly class NestedEachDataUriFilesDto extends AbstractDto
{
    public function __construct(
        #[Nested(
            type: \ApiSutra\VO\Files\Base64File::class,
            itemCast: DataUriBase64FileCast::class,
            each: 'value',
        )]
        public Base64FileCollection $faces,
    ) {}
}
