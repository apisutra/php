<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Validation;

use ApiSutra\Attributes\DataTransfer\Validate;
use ApiSutra\DataTransfer\AbstractDto;

final readonly class ScopedValidationDto extends AbstractDto
{
    public function __construct(
        #[Validate('fixture_rule')]
        public string $value = 'fixture-value',
    ) {}
}
