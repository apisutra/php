<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;

#[Get('/declared')]
#[Returns(SimpleResponseDto::class)]
final class OverrideEndpointRequest extends AbstractRequest
{
    protected function resolveEndpoint(): ?string
    {
        return '/runtime';
    }

    protected function resolveBaseUrl(): ?string
    {
        return 'https://runtime.test';
    }
}
