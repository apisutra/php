<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\MetadataIsolation;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

#[Get('/composite-defaults')]
#[Returns(DefaultsDto::class)]
final class CompositeDefaultsRequest extends AbstractRequest implements CompositeRequestInterface
{
    public function requests(): RequestCollection
    {
        return RequestCollection::make([new DefaultsRequest()]);
    }

    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed
    {
        return ['value' => 'aggregate'];
    }
}
