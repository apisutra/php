<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Dto;

use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Tests\Stubs\Enums\TestStatus;

abstract readonly class InheritedBaseStatusDto extends AbstractDto
{
    public function __construct(
        #[From('status')]
        public TestStatus $status,
    ) {}
}
