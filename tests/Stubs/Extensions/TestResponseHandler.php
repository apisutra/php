<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Extensions;

use ApiSutra\Contracts\Interfaces\Response\ResponseHandlerInterface;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;

final class TestResponseHandler implements ResponseHandlerInterface
{
    public function supports(ProviderResponse $response): bool
    {
        return true;
    }

    public function handle(ProviderResponse $response, PipelineContext $context): mixed
    {
        return [
            'handled' => true,
            'status' => $response->status,
        ];
    }
}
