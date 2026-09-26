<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\JsonContainerShapes;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use Override;

#[Get('/shape')]
final class ShapeRequest extends AbstractRequest
{
    public function __construct(private readonly Returns $returns = new Returns(Envelope::class))
    {
    }

    #[Override]
    public function getReturnsAttribute(): ?Returns
    {
        return $this->returns;
    }

    #[Override]
    public function getResponseType(): ?string
    {
        return $this->returns->response;
    }
}
