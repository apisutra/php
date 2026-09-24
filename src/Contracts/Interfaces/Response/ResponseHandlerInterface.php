<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Response;

use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;

interface ResponseHandlerInterface
{
    /**
     * Может ли обработать данный response
     */
    public function supports(ProviderResponse $response): bool;

    /**
     * Обработать response
     */
    public function handle(ProviderResponse $response, PipelineContext $context): mixed;
}
