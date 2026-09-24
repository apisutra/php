<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\DataTransfer\Validate;
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/invalid-composite')]
final class InvalidCompositeRequest extends AbstractRequest implements CompositeRequestInterface
{
    #[Validate('required')]
    public ?string $token = null;

    public function requests(): RequestCollection
    {
        return RequestCollection::make([
            new SimpleGetRequest('first'),
        ]);
    }

    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed
    {
        return $results->all();
    }
}
