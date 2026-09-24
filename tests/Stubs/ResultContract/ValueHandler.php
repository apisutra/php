<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ResultContract;

use ApiSutra\Contracts\Interfaces\Response\ResponseHandlerInterface;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;

final readonly class ValueHandler implements ResponseHandlerInterface
{
    public function __construct(private mixed $value)
    {
    }
    public function supports(ProviderResponse $response): bool
    {
        return true;
    }
    public function handle(ProviderResponse $response, PipelineContext $context): mixed
    {
        return $this->value;
    }
}
