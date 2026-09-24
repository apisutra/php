<?php

declare(strict_types=1);

namespace ApiSutra\Transport;

use ApiSutra\Contracts\Interfaces\Core\ConcurrentTransportInterface;
use ApiSutra\Execution\Async\GuzzlePromiseBridge;
use ApiSutra\Exceptions\Core\RuntimeException;
use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface;
use ApiSutra\Contracts\Interfaces\Core\FileStreamingInterface;
use ApiSutra\Contracts\Interfaces\Core\TimeoutAwareTransportInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Testing\MissingFixtureException;
use ApiSutra\Exceptions\Testing\UnmockedRequestException;
use ApiSutra\Files\DownloadManager;
use ApiSutra\Files\FileTransferGuard;
use ApiSutra\Http\DestinationGuard;
use ApiSutra\Http\RequestDestination;
use ApiSutra\Testing\Fixture;
use ApiSutra\Testing\MockConfig;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Testing\MockSequence;
use ApiSutra\VO\Files\FileTransferOptions;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Http\TransportOptions;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Override;
use Throwable;

final class MockTransport implements TimeoutAwareTransportInterface, ConcurrentTransportInterface, DestinationAwareInterface, FileStreamingInterface
{
    public function assertSupportsConcurrency(): void
    {
    }

    /** Fake не добавляет credentials и не выполняет redirects. */
    public function assertSupportsFileTransfer(FileTransferOptions $options): void
    {
    }

    public function assertSupportsDestination(RequestDestination $destination): void
    {
    }

    /** Fake принимает опции для тестов; реальный HTTP не выполняется. */
    public function assertSupportsTimeouts(TransportOptions $options): void
    {
    }

    /**
     * @var array<string, mixed>
     */
    private array $responses = [];

    /**
     * @var array<int, PreparedRequest>
     */
    private array $recorded = [];

    private bool $preventStray = false;
    private ?string $unmockedRequest = null;

    /** Первый пропущенный ответ сохраняется независимо от доставки FAILED. */
    public function unmockedRequest(): ?string
    {
        return $this->unmockedRequest;
    }

    public function fake(array $responses): void
    {
        $this->responses = $responses;
    }

    public function loadFixtures(string $path): void
    {
        $files = glob(rtrim($path, '/') . '/*.json') ?: [];
        $grouped = [];

        foreach ($files as $file) {
            $data = json_decode((string) file_get_contents($file), true, 512, JSON_BIGINT_AS_STRING);
            if (!is_array($data)) {
                continue;
            }

            $class = $data['request']['class'] ?? null;
            if (!is_string($class)) {
                continue;
            }

            if (($data['request']['bodyOmitted'] ?? false) || ($data['response']['bodyOmitted'] ?? false)) {
                throw new ConfigurationException(new Message('transport.fixture_does_not_contain_a_file_body_use_mockresponse'));
            }
            $response = $data['response'] ?? [];
            $body = $response['body'] ?? [];
            $status = (int) ($response['status'] ?? 200);
            $headers = $this->normalizeFixtureHeaders($response['headers'] ?? []);

            $grouped[$class] ??= [];
            $grouped[$class][] = new MockResponse($body, $status, $headers);
        }

        foreach ($grouped as $class => $responses) {
            $this->responses[$class] = count($responses) > 1
                ? new MockSequence($responses)
                : $responses[0];
        }
    }

    public function preventStrayRequests(): void
    {
        $this->preventStray = true;
    }

    #[Override]
    public function send(PreparedRequest $request): ProviderResponse
    {
        DestinationGuard::checkRequest($request);
        FileTransferGuard::checkCapability($this, FileTransferGuard::options($request));
        DestinationGuard::checkCapability($this, $request->destination);
        $this->recorded[] = $request;

        try {
            $response = $this->findResponse($request);
        } catch (MissingFixtureException $exception) {
            if ($this->preventStray) {
                $this->unmockedRequest ??= (string) ($request->meta['requestClass'] ?? 'unknown');
            }
            throw $exception;
        }
        if ($response === null && $this->preventStray) {
            $this->unmockedRequest ??= (string) ($request->meta['requestClass'] ?? 'unknown');
            throw new UnmockedRequestException(new Message('transport.unmocked_request'));
        }

        $response ??= MockResponse::notFound()->toProviderResponse($request);
        if ($request->fileTransfer?->download && $response->stream === null) {
            $sink = DownloadManager::temporary($request->fileTransfer->target);
            $body = $response->body ?? '';
            for ($offset = 0, $length = strlen($body); $offset < $length; $offset += $count) {
                $count = $sink->write(substr($body, $offset, 65536));
            }
            $sink->rewind();
            return new ProviderResponse($response->status, $response->headers, null, $request, $response->duration, $sink);
        }
        return $response;
    }

    #[Override]
    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        $promise = new Promise();

        try {
            $promise->resolve($this->send($request));
        } catch (Throwable $exception) {
            $promise->reject($exception);
        }

        return (new GuzzlePromiseBridge())->wrap($promise);
    }

    /**
     * @return array<int, PreparedRequest>
     */
    public function getRecorded(): array
    {
        return $this->recorded;
    }

    public function assertSent(string $requestClass, ?callable $callback = null, ?int $times = null): void
    {
        $count = 0;
        foreach ($this->recorded as $recorded) {
            $class = $recorded->meta['requestClass'] ?? null;
            if ($class !== $requestClass) {
                continue;
            }

            $instance = $recorded->meta['requestInstance'] ?? $recorded;
            if ($callback === null || $callback($instance)) {
                $count++;
            }
        }

        if ($times !== null) {
            if ($count !== $times) {
                throw new RuntimeException(new Message('transport.request_was_sent_time_s', ['requestClass' => $requestClass, 'count' => $count]));
            }
            return;
        }

        if ($count === 0) {
            throw new RuntimeException(new Message('transport.request_was_not_sent', ['requestClass' => $requestClass]));
        }
    }

    public function assertNotSent(string $requestClass): void
    {
        foreach ($this->recorded as $recorded) {
            $class = $recorded->meta['requestClass'] ?? null;
            if ($class === $requestClass) {
                throw new RuntimeException(new Message('transport.request_was_sent', ['requestClass' => $requestClass]));
            }
        }
    }

    public function assertNothingSent(): void
    {
        if ($this->recorded !== []) {
            throw new RuntimeException(new Message('transport.requests_were_sent'));
        }
    }

    private function findResponse(PreparedRequest $request): ?ProviderResponse
    {
        $requestClass = $request->meta['requestClass'] ?? null;
        if ($requestClass !== null && array_key_exists($requestClass, $this->responses)) {
            return $this->resolveResponse($this->responses[$requestClass], $request);
        }

        foreach ($this->responses as $pattern => $response) {
            if ($pattern === '*' || $this->matches($request->url, (string) $pattern)) {
                return $this->resolveResponse($response, $request);
            }
        }

        $fixturePath = MockConfig::getFixturePath();
        if ($fixturePath !== null && MockConfig::shouldThrowOnMissingFixtures()) {
            throw new MissingFixtureException(new Message('transport.fixture_not_found_for_request', ['requestClass' => $requestClass]));
        }

        return null;
    }

    private function resolveResponse(mixed $response, PreparedRequest $request): ?ProviderResponse
    {
        if ($response instanceof ProviderResponse) {
            return $response;
        }

        if ($response instanceof MockSequence) {
            return $response->next()->toProviderResponse($request);
        }

        if ($response instanceof MockResponse) {
            return $response->toProviderResponse($request);
        }

        if ($response instanceof Fixture) {
            return $this->resolveFixture($response, $request);
        }

        if (is_callable($response)) {
            $result = $response($request->meta['requestInstance'] ?? $request);
            return $this->resolveResponse($result, $request);
        }

        if (is_array($response) || is_string($response)) {
            return MockResponse::make($response)->toProviderResponse($request);
        }

        return null;
    }

    private function resolveFixture(Fixture $fixture, PreparedRequest $request): ?ProviderResponse
    {
        $path = MockConfig::getFixturePath();
        if ($path === null) {
            throw new MissingFixtureException(new Message('transport.fixture_path_is_not_specified'));
        }

        $file = rtrim($path, '/') . '/' . $fixture->name();
        if (!str_ends_with($file, '.json')) {
            $file .= '.json';
        }

        if (!file_exists($file)) {
            throw new MissingFixtureException(new Message('transport.fixture_not_found', ['file' => $file]));
        }

        $data = json_decode((string) file_get_contents($file), true, 512, JSON_BIGINT_AS_STRING);
        if (!is_array($data)) {
            throw new MissingFixtureException(new Message('transport.fixture_is_corrupted', ['file' => $file]));
        }

        if (($data['request']['bodyOmitted'] ?? false) || ($data['response']['bodyOmitted'] ?? false)) {
            throw new ConfigurationException(new Message('transport.fixture_does_not_contain_a_file_body_use_mockresponse'));
        }
        $response = $data['response'] ?? [];
        $body = $response['body'] ?? [];
        $status = (int) ($response['status'] ?? 200);
        $headers = $this->normalizeFixtureHeaders($response['headers'] ?? []);

        return (new MockResponse($body, $status, $headers))->toProviderResponse($request);
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, string>
     */
    private function normalizeFixtureHeaders(array $headers): array
    {
        $result = [];
        foreach ($headers as $name => $value) {
            if (is_array($value)) {
                $result[$name] = (string) ($value[0] ?? '');
                continue;
            }
            $result[$name] = (string) $value;
        }

        return $result;
    }

    private function matches(string $url, string $pattern): bool
    {
        if ($pattern === '*') {
            return true;
        }

        $pattern = str_replace('*', '.*', preg_quote($pattern, '/'));
        return (bool) preg_match('/' . $pattern . '/i', $url);
    }
}
