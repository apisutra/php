<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Validation;

use ApiSutra\Attributes\DataTransfer\Label;
use ApiSutra\Attributes\DataTransfer\Validate;
use ApiSutra\Attributes\Http\Post;
use ApiSutra\Attributes\Request\Body;
use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use ApiSutra\Contracts\Interfaces\Validation\CustomValidatableRequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\VO\Errors\ValidationError;
use ApiSutra\VO\Pipeline\PipelineContext;
use Override;

#[Post('/validation-order')]
final class ValidationOrderRequest extends AbstractRequest implements CompositeRequestInterface, CustomValidatableRequestInterface
{
    public int $customCalls = 0;
    public int $compositeCalls = 0;

    #[Validate('required', message: 'attribute :attribute')]
    #[Label('Email')]
    public string $email = '';

    #[Validate('required')]
    public string $name = '';

    #[Body]
    public string $payload = "\xB1";

    #[Override]
    public function validateCustom(): array
    {
        $this->customCalls++;
        return [new ValidationError('document', 'fixture', 'custom error', null)];
    }

    #[Override]
    public function requests(): RequestCollection
    {
        $this->compositeCalls++;
        return RequestCollection::make([]);
    }

    #[Override]
    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed
    {
        return $results->all();
    }

    /** @return array<string, string> */
    #[Override]
    protected static function validationMessages(): array
    {
        return ['email.required' => 'class email', 'name.required' => 'class name'];
    }
}
