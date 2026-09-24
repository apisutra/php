<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;
use Override;

#[Get('/hydration-probe')]
final class HydrationProbeRequest extends AbstractRequest
{
    public function __construct(private readonly string $dtoType) {}

    #[Override]
    public function getResponseType(): ?string
    {
        return $this->dtoType;
    }
}
