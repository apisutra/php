<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\PolymorphicInput;

use ApiSutra\Collections\RequestCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Continuation\UndeclaredRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

final class ReadyComposite extends AbstractRequest implements CompositeRequestInterface
{
    /** @param array<array-key, mixed>|object $value */
    public function __construct(private readonly string $type, private readonly array|object $value) {}

    public function getResponseType(): ?string { return $this->type; }

    public function requests(): RequestCollection
    {
        return RequestCollection::make([new UndeclaredRequest()]);
    }

    public function aggregate(ResultCollection $results, PipelineContext $ctx): mixed
    {
        return $this->value;
    }
}
