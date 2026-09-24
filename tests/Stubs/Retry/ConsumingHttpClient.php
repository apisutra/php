<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Retry;

use ApiSutra\Contracts\Interfaces\Core\AsyncHttpClientInterface;
use ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface;
use ApiSutra\Http\RequestDestination;
use ApiSutra\VO\Http\TransportOptions;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use ApiSutra\Contracts\Interfaces\Core\FileStreamingInterface;
use ApiSutra\VO\Files\FileTransferOptions;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use Throwable;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Promise\Create;
use ApiSutra\Execution\Async\GuzzlePromiseBridge;

final class ConsumingHttpClient implements ClientInterface, AsyncHttpClientInterface, DestinationAwareInterface, FileStreamingInterface
{
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
        if ($options->download) {
            throw new ConfigurationException('Тестовый адаптер поддерживает только upload');
        }
    }

    public function assertSupportsDestination(RequestDestination $destination): void
    {
    }

    /** @var list<TransportOptions> */
    public array $options = [];

    public function assertSupportsTimeouts(TransportOptions $options): void
    {
    }

    public function sendWithOptions(RequestInterface $request, TransportOptions $options): ResponseInterface
    {
        $this->options[] = $options->effective();
        return $this->sendRequest($request);
    }

    /** @var list<string> */
    public array $bodies = [];
    /** @var list<RequestInterface> */
    public array $requests = [];

    /** @param list<ResponseInterface|Throwable> $results */
    public function __construct(private array $results)
    {
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->bodies[] = $request->getBody()->getContents();
        $this->requests[] = $request;
        $result = array_shift($this->results) ?? throw new RuntimeException('Неожиданный HTTP-вызов');
        if ($result instanceof Throwable) {
            throw $result;
        }
        return $result;
    }
}
