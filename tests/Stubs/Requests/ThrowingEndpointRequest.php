<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Requests;

use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;
use RuntimeException;

#[Get('/throw-endpoint')]
final class ThrowingEndpointRequest extends AbstractRequest
{
    protected function resolveEndpoint(): ?string
    {
        throw new RuntimeException('Ошибка подготовки endpoint');
    }
}
