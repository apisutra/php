<?php

declare(strict_types=1);

namespace Example\ResultErrors;

use ApiSutra\Contracts\Interfaces\Response\ResponseHandlerInterface;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;

/** Намеренная ошибка расширения: строка вместо DTO для проверки защиты Returns. */
final class BrokenResponseHandler implements ResponseHandlerInterface
{
    public function supports(ProviderResponse $response): bool
    {
        return true;
    }

    public function handle(ProviderResponse $response, PipelineContext $context): mixed
    {
        return 'строка вместо AccountInfo';
    }
}
