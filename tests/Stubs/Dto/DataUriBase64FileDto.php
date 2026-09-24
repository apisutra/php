<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Casts\DataUriBase64FileCast;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\VO\Files\Base64File;

final readonly class DataUriBase64FileDto extends AbstractDto
{
    public function __construct(
        #[From('document')]
        #[Cast(DataUriBase64FileCast::class)]
        public ?Base64File $document = null,
    ) {}
}
