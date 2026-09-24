<?php

declare(strict_types=1);

namespace ApiSutra\Transport;

use ApiSutra\Contracts\Interfaces\Core\AsyncHttpClientInterface;
use ApiSutra\Contracts\Interfaces\Core\ConcurrentTransportInterface;
use ApiSutra\Execution\Async\GuzzlePromiseBridge;
use ApiSutra\Execution\Async\AwaitablePromise;
use GuzzleHttp\Promise\RejectedPromise;
use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface;
use ApiSutra\Contracts\Interfaces\Core\FileStreamingInterface;
use ApiSutra\VO\Files\FileTransferOptions;
use ApiSutra\Http\RequestDestination;
use ApiSutra\Http\DestinationGuard;
use ApiSutra\Http\RequestBodyGuard;
use ApiSutra\Files\FileTransferGuard;
use ApiSutra\Files\DownloadManager;
use ApiSutra\Files\BorrowedStream;
use ApiSutra\Contracts\Interfaces\Core\HttpClientOptionsInterface;
use ApiSutra\Contracts\Interfaces\Core\TimeoutAwareTransportInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Http\TransportOptions;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\HttpFactory;
use Override;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface as PsrRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

final class HttpTransport implements TimeoutAwareTransportInterface, ConcurrentTransportInterface, DestinationAwareInterface, FileStreamingInterface
{
    public function assertSupportsConcurrency(): void
    {
        $this->asyncHttpClient()->assertSupportsConcurrency();
    }

    private function asyncHttpClient(): AsyncHttpClientInterface
    {
        if (!$this->httpClient instanceof AsyncHttpClientInterface) {
            throw new ConfigurationException(new Message('transport.concurrent_execution_unsupported', ['adapter' => $this->httpClient::class]));
        }
        return $this->httpClient;
    }

    public function assertSupportsFileTransfer(FileTransferOptions $options): void
    {
        FileTransferGuard::checkCapability($this->httpClient, $options);
    }

    public function assertSupportsDestination(RequestDestination $destination): void
    {
        DestinationGuard::checkCapability($this->httpClient, $destination);
    }

    public function __construct(
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    /** Штатная сборка без ручного выбора адаптера и фабрик. */
    public static function createDefault(): self
    {
        $factory = new HttpFactory();
        return new self(new GuzzleHttpClient(), $factory, $factory);
    }

    public function assertSupportsTimeouts(TransportOptions $options): void
    {
        if ($this->httpClient instanceof HttpClientOptionsInterface) {
            $this->httpClient->assertSupportsTimeouts($options);
        } elseif ($options->hasLimits()) {
            throw new ConfigurationException(new Message('transport.does_not_support_per_request_timeout_connecttimeout_deadline_httpclientoptionsinterface', ['value0' => $this->httpClient::class]));
        }
    }

    #[Override]
    public function send(PreparedRequest $request): ProviderResponse
    {
        $exchange = $this->prepare($request);
        try {
            $response = $exchange->options !== null && $this->httpClient instanceof HttpClientOptionsInterface
                ? $this->httpClient->sendWithOptions($exchange->request, $exchange->options)
                : $this->httpClient->sendRequest($exchange->request);
            return $exchange->complete($response);
        } catch (Throwable $exception) {
            $exchange->close();
            throw TransportExceptionNormalizer::normalize($exception);
        }
    }

    #[Override]
    public function sendAsync(PreparedRequest $request): PromiseInterface
    {
        $bridge = new GuzzlePromiseBridge();
        $exchange = null;
        try {
            $this->assertSupportsConcurrency();
            $exchange = $this->prepare($request);
            $source = $this->asyncHttpClient()->sendAsyncWithOptions($exchange->request, $exchange->options ?? new TransportOptions());
        } catch (Throwable $exception) {
            $exchange?->close();
            return $bridge->wrap(new RejectedPromise(TransportExceptionNormalizer::normalize($exception)));
        }
        $result = new Promise(null, static function () use ($source, $exchange): void {
            try {
                $source->cancel();
            } finally {
                $exchange->close();
            }
        });
        $source->then(
            static function (ResponseInterface $response) use ($exchange, $result, $bridge): void {
                if ($result->getState() !== PromiseInterface::PENDING) {
                    return;
                }
                try {
                    $result->resolve($exchange->complete($response));
                } catch (Throwable $exception) {
                    $exchange->close();
                    $result->reject(TransportExceptionNormalizer::normalize($exception));
                }
                $bridge->pump();
            },
            static function (mixed $reason) use ($exchange, $result, $bridge): void {
                $exchange->close();
                if ($result->getState() === PromiseInterface::PENDING) {
                    $result->reject($reason instanceof Throwable ? TransportExceptionNormalizer::normalize($reason) : $reason);
                }
                $bridge->pump();
            },
        );
        return $bridge->wrap($result, abandon: static function () use ($source, $exchange): void {
            try {
                $source instanceof AwaitablePromise ? $source->abandon() : $source->cancel();
            } finally {
                $exchange->close();
            }
        });
    }

    private function prepare(PreparedRequest $request): HttpExchange
    {
        RequestBodyGuard::check($request);
        DestinationGuard::checkRequest($request);
        FileTransferGuard::checkCapability($this, FileTransferGuard::options($request));
        DestinationGuard::checkCapability($this, $request->destination);
        $start = microtime(true);
        $psrRequest = $this->buildPsrRequest($request);
        $options = $request->transportOptions;
        $transfer = FileTransferGuard::options($request);
        $sink = $transfer?->download ? DownloadManager::temporary($transfer->target) : null;
        if ($request->destination !== null || $transfer !== null) {
            $options = new TransportOptions(
                $options->timeoutMs ?? 0,
                $options->connectTimeoutMs ?? 0,
                $options?->budget,
                $request->destination,
                $transfer,
                $sink === null ? null : new BorrowedStream($sink),
            );
        }
        if ($options !== null) {
            $this->assertSupportsTimeouts($options);
            $options = $options->effective();
        }
        return new HttpExchange($request, $psrRequest, $options, $sink, $start);
    }

    private function buildPsrRequest(PreparedRequest $request): PsrRequestInterface
    {
        $psrRequest = $this->requestFactory->createRequest($request->method->value, $request->url);

        foreach ($request->headers as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }

        if ($request->stream !== null) {
            $psrRequest = $psrRequest->withBody(new BorrowedStream($request->stream));
        } elseif ($request->body !== null) {
            $psrRequest = $psrRequest->withBody($this->streamFactory->createStream($request->body));
        }

        if ($request->destination?->preserveUrl) {
            $target = $request->destination->requestTarget();
            // Фабрика URI может потерять пустой '?'; request target задаётся отдельно.
            if ($psrRequest->getRequestTarget() !== rtrim($target, '?') && $psrRequest->getRequestTarget() !== $target) {
                throw new ConfigurationException(new Message('transport.psr_factory_modifies_the_absolute_request_target'));
            }
            $psrRequest = $psrRequest->withRequestTarget($target);
        }
        return $psrRequest;
    }
}
