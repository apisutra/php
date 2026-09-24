<?php

declare(strict_types=1);

namespace ApiSutra\Transport;

use ApiSutra\Contracts\Interfaces\Core\AsyncHttpClientInterface;
use GuzzleHttp\Promise\PromiseInterface;
use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Core\DestinationAwareInterface;
use ApiSutra\Contracts\Interfaces\Core\FileStreamingInterface;
use ApiSutra\VO\Files\FileTransferOptions;
use ApiSutra\Http\RequestDestination;
use ApiSutra\Http\Origin;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\VO\Http\TransportOptions;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** Штатный адаптер с известным cURL handler; Guzzle остаётся опциональной зависимостью. */
final class GuzzleHttpClient implements ClientInterface, AsyncHttpClientInterface, DestinationAwareInterface, FileStreamingInterface
{
    public function assertSupportsFileTransfer(FileTransferOptions $options): void
    {
        if ($this->customCurl) {
            throw new ConfigurationException(new Message('transport.low_level_curl_overrides_are_incompatible_with_streaming_files'));
        }
    }

    public function assertSupportsDestination(RequestDestination $destination): void
    {
        if ($this->customCurl) {
            throw new ConfigurationException(new Message('transport.low_level_curl_overrides_are_incompatible_with_destination_isolation'));
        }
    }

    private ?GuzzleAsyncDriver $asyncClient = null;
    private ?GuzzleAsyncDriver $isolatedAsyncClient = null;
    /** @var array<string, mixed> */
    private readonly array $config;
    /** @var array<string, mixed> */
    private readonly array $isolatedConfig;
    private readonly Client $client;
    private readonly Client $isolatedClient;
    private readonly bool $customCurl;

    /** @param array<string, mixed> $config Настройки клиента без подмены HTTP handler. */
    public function __construct(array $config = [])
    {
        if (!class_exists(Client::class) || !extension_loaded('curl')) {
            throw new ConfigurationException(new Message('transport.default_http_adapter_requires_guzzlehttp_guzzle_and_ext_curl'));
        }
        if (array_key_exists('handler', $config)) {
            throw new ConfigurationException(new Message('transport.for_a_custom_guzzle_handler_use_an_httpclientoptionsinterface_adapter'));
        }
        $this->config = $config;
        $this->customCurl = ($config['curl'] ?? []) !== [];
        // Отдельный клиент не наследует auth, cookies, сертификат клиента и payload defaults.
        $isolated = array_intersect_key($config, array_flip(['verify', 'proxy', 'force_ip_resolve', 'version', 'timeout', 'connect_timeout', 'read_timeout']));
        $this->isolatedConfig = $isolated;
        $isolated['handler'] = HandlerStack::create(new CurlHandler(['handle_factory' => new ExactTargetCurlFactory()]));
        $this->isolatedClient = new Client($isolated);
        $config['handler'] = HandlerStack::create(new CurlHandler());
        $this->client = new Client($config);
    }

    public function assertSupportsConcurrency(): void
    {
        if ((curl_version()['features'] & CURL_VERSION_ASYNCHDNS) === 0) {
            throw new ConfigurationException(new Message('transport.async_dns_required'));
        }
    }

    public function sendAsyncWithOptions(RequestInterface $request, TransportOptions $options): PromiseInterface
    {
        $this->assertSupportsConcurrency();
        $resolved = $this->requestOptions($request, $options);
        $driver = $options->destination?->requiresIsolation()
            ? ($this->isolatedAsyncClient ??= new GuzzleAsyncDriver($this->isolatedConfig, true))
            : ($this->asyncClient ??= new GuzzleAsyncDriver($this->config));
        return $driver->send($request, $resolved, $options);
    }

    public function assertSupportsTimeouts(TransportOptions $options): void
    {
        // cURL применяет оба таймаута с точностью до миллисекунды.
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        return $this->client->sendRequest($request);
    }

    public function sendWithOptions(RequestInterface $request, TransportOptions $options): ResponseInterface
    {
        $resolved = $this->requestOptions($request, $options);
        $client = $options->destination?->requiresIsolation() ? $this->isolatedClient : $this->client;
        return $client->send($request, $resolved);
    }

    /** @return array<string, mixed> */
    private function requestOptions(RequestInterface $request, TransportOptions $options): array
    {
        $effective = $options->effective();
        $destination = $effective->destination;
        $fileOptions = [];
        if ($effective->fileTransfer !== null) {
            $this->assertSupportsFileTransfer($effective->fileTransfer);
            $fileOptions = [
                'sink' => $effective->sink,
                'debug' => false,
                'body' => null,
                'json' => null,
                'form_params' => null,
                'multipart' => null,
            ];
            if ($effective->fileTransfer->download && $effective->sink === null) {
                throw new ConfigurationException(new Message('transport.streaming_download_requires_a_sink'));
            }
        }
        if ($destination?->requiresIsolation()) {
            $this->assertSupportsDestination($destination);
            if (
                Origin::fromUrl((string) $request->getUri()) !== $destination->origin
                || ($destination->preserveUrl && $request->getRequestTarget() !== $destination->requestTarget())
            ) {
                throw new ConfigurationException(new Message('transport.http_client_received_a_changed_request_destination'));
            }
            return [
                'timeout' => $effective->timeoutMs / 1000,
                'connect_timeout' => $effective->connectTimeoutMs / 1000,
                'http_errors' => false,
                'allow_redirects' => false,
                'cookies' => false,
            ] + $fileOptions;
        }
        return [
            'timeout' => $effective->timeoutMs / 1000,
            'connect_timeout' => $effective->connectTimeoutMs / 1000,
            'http_errors' => false,
            'allow_redirects' => false,
        ] + $fileOptions;
    }
}
