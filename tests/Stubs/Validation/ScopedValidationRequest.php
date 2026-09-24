<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Validation;

use ApiSutra\Attributes\DataTransfer\Validate;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;

#[Get('/validation-probe')]
final class ScopedValidationRequest extends AbstractRequest
{
    public function __construct(
        #[Validate('fixture_rule')]
        public string $value = 'fixture-value',
    ) {}
}
