<?php

declare(strict_types=1);

namespace ApiSutra\Transport;

use ApiSutra\Diagnostics\SensitiveFields;
use ApiSutra\Contracts\Interfaces\Core\ConcurrentTransportInterface;
use ApiSutra\Execution\Async\GuzzlePromiseBridge;
use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface;
use ApiSutra\Contracts\Interfaces\Core\FileStreamingInterface;
use ApiSutra\Contracts\Interfaces\Core\TimeoutAwareTransportInterface;
use ApiSutra\Contracts\Interfaces\Core\TransportInterface;
use ApiSutra\Diagnostics\RedactionPolicy;
use ApiSutra\Files\FileTransferGuard;
use ApiSutra\Http\DestinationGuard;
use ApiSutra\Http\RequestDestination;
use ApiSutra\Testing\Fixture;
use ApiSutra\Testing\FixtureRedactor;
use ApiSutra\VO\Files\FileTransferOptions;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Http\TransportOptions;
use GuzzleHttp\Promise\PromiseInterface;
use ReflectionClass;
use ApiSutra\Exceptions\Testing\RecordingException;
use ErrorException;
use ApiSutra\Exceptions\Core\RuntimeException;
use ApiSutra\Exceptions\Core\UnexpectedValueException;
use Throwable;
use Override;
use GuzzleHttp\Promise\RejectedPromise;

final class RecordingTransport implements TimeoutAwareTransportInterface, ConcurrentTransportInterface, DestinationAwareInterface, FileStreamingInterface
{
    public function assertSupportsConcurrency(): void
    {
        TransportExecution::assertConcurrent($this->transport);
    }

    public function assertSupportsFileTransfer(FileTransferOptions $options): void
    {
        FileTransferGuard::checkCapability($this->transport, $options);
    }

    public function assertSupportsDestination(RequestDestination $destination): void
    {
        DestinationGuard::checkCapability($this->transport, $destination);
    }

    public function assertSupportsTimeouts(TransportOptions $options): void
    {
        TransportCapabilities::check($this->transport, $options);
    }

    /**
     * @var array<string, Fixture>
     */
    private array $fixtures = [];
    private FixtureRedactor $redactor;

    /**
     * @param array<string, Fixture> $fixtures
     */
    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $path,
        array $fixtures = [],
        RedactionPolicy $redaction = new RedactionPolicy(),
    ) {
        $this->fixtures = $fixtures;
        $this->redactor = new FixtureRedactor($redaction);
    }

    #[Override]
    public function send(PreparedRequest $request): ProviderResponse
    {
        DestinationGuard::checkRequest($request);
        FileTransferGuard::checkCapability($this, FileTransferGuard::options($request));
        DestinationGuard::checkCapability($this, $request->destination);
        $response = $this->transport->send($request);
        try {
            $this->record($request, $response);
        } catch (Throwable $exception) {
            throw new RecordingException($response, $exception);
        }
        return $response;
    }

    #[Override]
    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        try {
            $this->assertSupportsConcurrency();
            DestinationGuard::checkRequest($request);
            FileTransferGuard::checkCapability($this, FileTransferGuard::options($request));
            DestinationGuard::checkCapability($this, $request->destination);
            return (new GuzzlePromiseBridge())->wrap($this->transport->sendAsync($request))->then(
                function (ProviderResponse $response) use ($request): ProviderResponse {
                    try {
                        $this->record($request, $response);
                    } catch (Throwable $exception) {
                        throw new RecordingException($response, $exception);
                    }
                    return $response;
                },
            );
        } catch (Throwable $exception) {
            return (new GuzzlePromiseBridge())->wrap(new RejectedPromise($exception));
        }
    }

    private function record(PreparedRequest $request, ProviderResponse $response): void
    {
        foreach ([$request->body, $response->body] as $body) {
            if ($body !== null && preg_match('//u', $body) !== 1) {
                throw new UnexpectedValueException(new Message('transport.body_cannot_be_represented_in_the_utf_8_fixture'));
            }
        }
        $payload = [
            'request' => [
                'class' => $request->meta['requestClass'] ?? null,
                'method' => $request->method->value,
                'url' => $request->destination?->preserveUrl ? $request->destination->diagnosticUrl() : $request->url,
                'headers' => $request->headers,
                'body' => $this->normalizeBody($request->body, $request->headers['Content-Type'] ?? null),
                'bodyOmitted' => $request->stream !== null,
                'size' => $request->stream?->getSize(),
            ],
            'response' => [
                'status' => $response->status,
                'headers' => $response->headers,
                'body' => $this->normalizeBody($response->body, $response->header('Content-Type')),
                'bodyOmitted' => $response->stream !== null,
                'size' => $response->stream?->getSize(),
            ],
            'recorded_at' => gmdate('c'),
        ];

        $fixture = $this->resolveFixture($request);
        $secretFields = SensitiveFields::of($request);
        $payload = $this->redactor->redact(
            $payload,
            $fixture,
            $secretFields,
        );

        $payload = $request->destination?->redactReferences($payload) ?? $payload;
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
        $this->publish($payload['request']['class'], $json);
    }

    private function resolveFixture(PreparedRequest $request): ?Fixture
    {
        $class = $request->meta['requestClass'] ?? null;
        if (!is_string($class)) {
            return null;
        }

        return $this->fixtures[$class] ?? null;
    }

    /** Публикует целую fixture без перезаписи файла другого recorder. */
    private function publish(?string $requestClass, string $json): void
    {
        $temporary = null;
        set_error_handler(static function (int $severity, string $message, string $file, int $line): never {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });
        try {
            if (!is_dir($this->path)) {
                try {
                    if (!mkdir($this->path, 0777, true)) {
                        throw new RuntimeException(new Message('transport.failed_to_create_the_fixtures_directory'));
                    }
                } catch (Throwable $exception) {
                    if (!is_dir($this->path)) {
                        throw $exception;
                    }
                }
            }
            $temporary = tempnam($this->path, '.recording-');
            if ($temporary === false || realpath(dirname($temporary)) !== realpath($this->path)) {
                throw new RuntimeException(new Message('transport.failed_to_create_a_temporary_fixture_in_the_target'));
            }
            if (file_put_contents($temporary, $json) !== strlen($json)) {
                throw new RuntimeException(new Message('transport.fixture_was_not_written_completely'));
            }
            $base = $requestClass !== null ? (new ReflectionClass($requestClass))->getShortName() : 'request';
            for ($index = 1;; $index++) {
                $candidate = $this->path . '/' . $base . '_' . $index . '.json';
                try {
                    // link атомарно отказывает, если другой процесс уже занял имя.
                    if (!link($temporary, $candidate)) {
                        throw new RuntimeException(new Message('transport.failed_to_publish_the_fixture'));
                    }
                    break;
                } catch (Throwable $exception) {
                    if (!file_exists($candidate) && !is_link($candidate)) {
                        throw $exception;
                    }
                }
            }
            if (!unlink($temporary)) {
                throw new RuntimeException(new Message('transport.failed_to_remove_the_temporary_fixture'));
            }
            $temporary = null;
        } finally {
            restore_error_handler();
            if (is_string($temporary) && is_file($temporary)) {
                // Удаляется только собственный временный файл, опубликованные файлы не затрагиваются.
                @unlink($temporary);
            }
        }
    }

    private function normalizeBody(?string $body, ?string $contentType): array|string|null
    {
        if ($body === null) {
            return null;
        }

        $isJson = $contentType !== null && str_contains((string) $contentType, 'json');
        if ($isJson) {
            $decoded = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        $decoded = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);
        return is_array($decoded) ? $decoded : $body;
    }
}
