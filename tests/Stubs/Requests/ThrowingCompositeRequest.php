<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\VO\Pipeline\PipelineContext;
use RuntimeException;

#[Get('/composite-throw')]
final class ThrowingCompositeRequest extends AbstractRequest implements CompositeRequestInterface
{
    public function requests(): RequestCollection
    {
        throw new RuntimeException('Ошибка построения composite');
    }

    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed
    {
        return $results->all();
    }
}
