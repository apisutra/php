<?php

declare(strict_types=1);

namespace ApiSutra\Testing;

use ApiSutra\Files\DownloadManager;
use ApiSutra\Files\StreamCopy;
use ApiSutra\VO\Files\FileInput;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;

/** Открывает новую ручку для каждой попытки и каждого выполнения. */
final readonly class MockFileResponse extends MockResponse
{
    /** @param array<string, string> $headers */
    public function __construct(private string $path, int $status = 200, array $headers = [])
    {
        parent::__construct('', $status, $headers);
    }

    public function toProviderResponse(PreparedRequest $request): ProviderResponse
    {
        $file = FileInput::fromPath($this->path);
        try {
            $sink = DownloadManager::temporary($request->fileTransfer?->target);
            StreamCopy::copy($file->stream, $sink, $request->transportOptions?->budget);
            $sink->rewind();
        } finally {
            $file->close();
        }
        $headers = [];
        foreach ($this->headers as $name => $value) {
            $headers[$name] = [$value];
        }
        return new ProviderResponse($this->status, $headers, null, $request, 0, $sink);
    }
}
