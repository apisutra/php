<?php

declare(strict_types=1);

namespace ApiSutra\Extensions\Archive\Handlers;

use ApiSutra\Contracts\Interfaces\Response\ResponseHandlerInterface;
use ApiSutra\Extensions\Archive\Response\ArchiveResponse;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;

final class ArchiveResponseHandler implements ResponseHandlerInterface
{
    #[\Override]
    public function supports(ProviderResponse $response): bool
    {
        $contentType = $response->header('Content-Type') ?? '';
        if (str_contains($contentType, 'zip') || str_contains($contentType, 'tar')) {
            return true;
        }

        if (str_contains($contentType, 'gzip')) {
            return true;
        }

        if (str_starts_with($response->body, "PK")) {
            return true;
        }

        return str_starts_with($response->body, "\x1F\x8B");
    }

    #[\Override]
    public function handle(ProviderResponse $response, PipelineContext $context): mixed
    {
        $format = $this->detectFormat($response);
        return new ArchiveResponse($response->body, $format, $context->config);
    }

    private function detectFormat(ProviderResponse $response): string
    {
        $contentType = $response->header('Content-Type') ?? '';
        if (str_contains($contentType, 'gzip')) {
            return 'tar.gz';
        }
        if (str_contains($contentType, 'zip')) {
            return 'zip';
        }
        if (str_contains($contentType, 'tar')) {
            return 'tar';
        }

        return 'zip';
    }
}
