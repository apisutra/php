<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Core;

use ApiSutra\Contracts\Interfaces\Core\AsyncHttpClientInterface;
use ApiSutra\VO\Http\TransportOptions;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use Throwable;
use ApiSutra\Contracts\Interfaces\Core\FileStreamingInterface;
use ApiSutra\VO\Files\FileTransferOptions;
use ApiSutra\Files\StreamCopy;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Create;
use ApiSutra\Execution\Async\GuzzlePromiseBridge;

final class SequenceHttpClient implements ClientInterface, AsyncHttpClientInterface, FileStreamingInterface
{
    /** @var list<TransportOptions> */
    public array $options = [];

    public function assertSupportsConcurrency(): void
    {
    }

    public function sendAsyncWithOptions(RequestInterface $request, TransportOptions $options): PromiseInterface
    {
        try {
            $promise = Create::promiseFor($this->sendWithOptions($request, $options));
        } catch (Throwable $error) {
            $promise = Create::rejectionFor($error);
        }
        return (new GuzzlePromiseBridge())->wrap($promise);
    }

    public function assertSupportsFileTransfer(FileTransferOptions $options): void
    {
        if ($options->upload) {
            throw new ConfigurationException('SequenceHttpClient поддерживает только download');
        }
    }

    public function assertSupportsTimeouts(TransportOptions $options): void
    {
    }

    public function sendWithOptions(RequestInterface $request, TransportOptions $options): ResponseInterface
    {
        $this->options[] = $options->effective();
        $response = $this->sendRequest($request);
        if ($options->sink !== null) {
            StreamCopy::copy($response->getBody(), $options->sink, $options->budget);
            return $response->withBody($options->sink);
        }
        return $response;
    }

    public int $calls = 0;

    /** @param list<ResponseInterface|Throwable> $results */
    public function __construct(private array $results)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $result = $this->results[$this->calls++] ?? throw new RuntimeException('Неожиданный HTTP-вызов');
        if ($result instanceof Throwable) {
            throw $result;
        }
        return $result;
    }
}
