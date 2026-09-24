<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Request\Query;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Contracts\Interfaces\Validation\CustomValidatableRequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use ApiSutra\VO\Errors\ValidationError;

#[Get('/custom-validatable')]
#[Returns(SimpleResponseDto::class)]
final class CustomValidatableRequestStub extends AbstractRequest implements CustomValidatableRequestInterface
{
    /** @var array<ValidationError> Ошибки для validateCustom() (тестовый stub). */
    public array $customValidationErrors = [];

    public function __construct(
        #[Query]
        public string $query = '',
    ) {}

    #[\Override]
    public function validateCustom(): array
    {
        return $this->customValidationErrors;
    }
}
