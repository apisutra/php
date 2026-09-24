<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Localization\Message;
use ApiSutra\Enums\Http\FileFormat;
use ApiSutra\Enums\Serialization\BooleanFormat;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\VO\Files\FileInput;
use ApiSutra\Files\BorrowedStream;
use GuzzleHttp\Psr7\MultipartStream;
use GuzzleHttp\Psr7\LimitStream;
use GuzzleHttp\Psr7\NoSeekStream;
use Psr\Http\Message\StreamInterface;
use Throwable;

final class FilePayloadPreparer
{
    /**
     * @param array<int, array{name: string, file: FileInput}> $files
     * @param mixed $body
     * @param array<string, string> $headers
     * @return array{body: ?string, stream: ?StreamInterface, headers: array<string, string>}
     */
    public function prepareBodyAndStream(
        ?FileFormat $fileFormat,
        array $files,
        mixed $body,
        bool $bodyIsRoot,
        array $headers,
        BooleanFormat $booleanFormat = BooleanFormat::Numeric,
    ): array {
        $preparedBody = (in_array($fileFormat, [FileFormat::Multipart, FileFormat::Base64], true)
            || ($fileFormat === FileFormat::Binary && ($files[0]['file'] ?? null) instanceof FileInput))
            ? null
            : $this->prepareJsonBody($body, $bodyIsRoot);

        return match ($fileFormat) {
            FileFormat::Multipart => $this->prepareMultipartBody($files, $body, $headers, $booleanFormat),
            FileFormat::Binary => $this->prepareBinaryBody($files, $preparedBody, $headers),
            FileFormat::Base64 => [
                'body' => JsonEncoder::encode($this->applyBase64Files($files, $body)),
                'stream' => null,
                'headers' => $this->ensureJsonContentType($headers),
            ],
            default => [
                'body' => $preparedBody,
                'stream' => null,
                'headers' => $preparedBody !== null
                    ? $this->ensureJsonContentType($headers)
                    : $headers,
            ],
        };
    }

    /**
     * @param array<int, array{name: string, file: FileInput}> $files
     * @param mixed $body
     * @param array<string, string> $headers
     * @return array{body: ?string, stream: MultipartStream, headers: array<string, string>}
     */
    private function prepareMultipartBody(array $files, mixed $body, array $headers, BooleanFormat $booleanFormat): array
    {
        if (!is_array($body)) {
            throw new ConfigurationException(new Message('serialization.multipart_payload_expects_an_array_body'));
        }

        $stream = $this->buildMultipartStream($files, $body, $booleanFormat);
        $headers['Content-Type'] = 'multipart/form-data; boundary=' . $stream->getBoundary();

        return [
            'body' => null,
            'stream' => $stream,
            'headers' => $headers,
        ];
    }

    /**
     * @param array<int, array{name: string, file: FileInput}> $files
     * @param array<string, string> $headers
     * @return array{body: ?string, stream: ?StreamInterface, headers: array<string, string>}
     */
    private function prepareBinaryBody(array $files, ?string $preparedBody, array $headers): array
    {
        $fileItem = $files[0]['file'] ?? null;
        if ($fileItem instanceof FileInput) {
            $headers['Content-Type'] = $fileItem->mimeType ?? 'application/octet-stream';
            return ['body' => null, 'stream' => $this->preserveFilePosition($fileItem->stream), 'headers' => $headers];
        }

        return [
            'body' => $preparedBody,
            'stream' => null,
            'headers' => $headers,
        ];
    }

    /**
     * @param array<int, array{name: string, file: FileInput}> $files
     * @param array<string, mixed> $body
     */
    private function buildMultipartStream(array $files, array $body, BooleanFormat $booleanFormat): MultipartStream
    {
        if (!class_exists(MultipartStream::class)) {
            throw new ConfigurationException(new Message('serialization.multipartstream_is_unavailable_guzzlehttp_psr7'));
        }

        $parts = [];

        foreach ($body as $key => $value) {
            $parts[] = [
                'name' => $key,
                'contents' => is_bool($value) ? $booleanFormat->format($value)
                    : (is_scalar($value) ? (string) $value : JsonEncoder::encode($value)),
            ];
        }

        foreach ($files as $fileItem) {
            $file = $fileItem['file'] ?? null;
            if (!$file instanceof FileInput) {
                continue;
            }

            $parts[] = [
                'name' => $fileItem['name'],
                'contents' => $this->preserveFilePosition($file->stream),
                // MultipartStream вставляет имя в quoted-string без экранирования.
                'filename' => strtr($file->filename, ['"' => '%22', "\r" => '%0D', "\n" => '%0A']),
                'headers' => $file->mimeType ? ['Content-Type' => $file->mimeType] : [],
            ];
        }

        return new MultipartStream($parts);
    }

    private function preserveFilePosition(StreamInterface $stream): StreamInterface
    {
        $stream = new BorrowedStream($stream);
        try {
            $offset = $stream->tell();
            return $offset > 0 ? new LimitStream($stream, -1, $offset) : $stream;
        } catch (Throwable) {
            // Без известной начальной позиции повтор файла не гарантирует прежние байты.
            return new NoSeekStream($stream);
        }
    }

    /**
     * @param array<int, array{name: string, file: FileInput}> $files
     * @param mixed $body
     * @return array<string, mixed>
     */
    private function applyBase64Files(array $files, mixed $body): array
    {
        if (!is_array($body)) {
            throw new ConfigurationException(new Message('serialization.base64_payload_expects_an_array_body'));
        }

        foreach ($files as $fileItem) {
            $file = $fileItem['file'] ?? null;
            if (!$file instanceof FileInput) {
                continue;
            }

            $body[$fileItem['name']] = base64_encode((string) $file->stream);
        }

        return $body;
    }

    /**
     * Добавляет Content-Type: application/json, если заголовок ещё не задан.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function ensureJsonContentType(array $headers): array
    {
        foreach (array_keys($headers) as $name) {
            if (strtolower($name) === 'content-type') {
                return $headers;
            }
        }

        $headers['Content-Type'] = 'application/json';

        return $headers;
    }

    private function prepareJsonBody(mixed $body, bool $bodyIsRoot): ?string
    {
        if ($body === null) {
            return null;
        }

        if (!$bodyIsRoot && is_array($body) && $body === []) {
            return null;
        }

        return JsonEncoder::encode($body);
    }
}
