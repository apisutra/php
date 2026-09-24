<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\DtoMapping;

use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Contracts\Interfaces\Validation\CustomValidatableRequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Exceptions\ControlFlow\EarlyReturnException;
use ApiSutra\VO\Errors\ValidationError;
use ApiSutra\VO\Pipeline\PipelineContext;
use Override;

#[Post('/records')]
final class ReturnsValidationRequest extends AbstractRequest implements CustomValidatableRequestInterface
{
    public bool $hookCalled = false;
    public int $validationCalls = 0;
    /** @var list<ValidationError> */
    public array $customValidationErrors = [];

    public function __construct(
        private ?Returns $declaration,
        private ?string $responseType = null,
        private bool $download = false,
        private bool $earlyReturn = false,
    ) {
    }

    #[Override]
    public function validateCustom(): array
    {
        $this->validationCalls++;
        return $this->customValidationErrors;
    }

    #[Override]
    public function getReturnsAttribute(): ?Returns
    {
        return $this->declaration;
    }

    #[Override]
    public function getResponseType(): ?string
    {
        return $this->responseType ?? $this->declaration?->response;
    }

    #[Override]
    public function hasDownload(): bool
    {
        return $this->download;
    }

    #[Override]
    protected function beforeSend(PipelineContext $context): void
    {
        $this->hookCalled = true;
        if ($this->earlyReturn) {
            throw new EarlyReturnException(['id' => 7]);
        }
    }
}
