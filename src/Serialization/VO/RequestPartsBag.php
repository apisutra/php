<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\VO;

use ApiSutra\Enums\Http\FileFormat;
use ApiSutra\Enums\Http\QueryArrayFormat;
use ApiSutra\VO\Files\FileInput;

final class RequestPartsBag
{
    /**
     * @param array<string, array{value: mixed, format: ?QueryArrayFormat}> $query
     * @param array<string, string> $headers
     * @param array<int, array{name: string, file: FileInput}> $files
     * @param array<string, mixed> $placeholders
     * @param array<string, mixed> $enrichment
     */
    public function __construct(
        public array $query,
        public mixed $body,
        public bool $bodyIsRoot,
        public array $headers,
        public array $files,
        public ?FileFormat $fileFormat,
        public array $placeholders,
        public array $enrichment = [],
    ) {
    }

    /**
     * @param array{
     *   query: array<string, array{value: mixed, format: ?QueryArrayFormat}>,
     *   body: mixed,
     *   bodyIsRoot: bool,
     *   headers: array<string, string>,
     *   files: array<int, array{name: string, file: FileInput}>,
     *   fileFormat: ?FileFormat,
     *   placeholders: array<string, mixed>
     * } $parts
     */
    public static function fromArray(array $parts): self
    {
        return new self(
            query: $parts['query'],
            body: $parts['body'],
            bodyIsRoot: $parts['bodyIsRoot'],
            headers: $parts['headers'],
            files: $parts['files'],
            fileFormat: $parts['fileFormat'],
            placeholders: $parts['placeholders'],
        );
    }

    /**
     * @return array{
     *   query: array<string, array{value: mixed, format: ?QueryArrayFormat}>,
     *   body: mixed,
     *   bodyIsRoot: bool,
     *   headers: array<string, string>,
     *   files: array<int, array{name: string, file: FileInput}>,
     *   fileFormat: ?FileFormat,
     *   placeholders: array<string, mixed>
     * }
     */
    public function toArray(): array
    {
        return [
            'query' => $this->query,
            'body' => $this->body,
            'bodyIsRoot' => $this->bodyIsRoot,
            'headers' => $this->headers,
            'files' => $this->files,
            'fileFormat' => $this->fileFormat,
            'placeholders' => $this->placeholders,
        ];
    }
}
