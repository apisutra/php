<?php

declare(strict_types=1);

use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Tests\Stubs\Core\TestHttpClient;
use ApiSutra\Tests\Stubs\Support\MapContainerProvider;
use ApiSutra\Transport\DefaultTransportFactory;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\VO\Http\PreparedRequest;
use GuzzleHttp\Psr7\HttpFactory;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

it('собирает PSR транспорт без Laravel и разрешает client, request factory, stream factory по порядку', function (): void {
    $client = new TestHttpClient();
    $factory = new HttpFactory();
    $provider = new MapContainerProvider([
        ClientInterface::class => $client,
        RequestFactoryInterface::class => $factory,
        StreamFactoryInterface::class => $factory,
    ]);
    $transport = (new DefaultTransportFactory())->create($provider);
    expect($provider->resolved)->toBe([ClientInterface::class, RequestFactoryInterface::class, StreamFactoryInterface::class])
        ->and($client->lastRequest)->toBeNull();
    $response = $transport->send(new PreparedRequest(HttpMethod::GET, 'https://fixture.test'));
    expect($response->body)->toBe('payload')->and((string) $client->lastRequest->getUri())->toBe('https://fixture.test');
});

it('использует Guzzle fallback и фабрики PSR по умолчанию', function (): void {
    expect((new DefaultTransportFactory())->create(new MapContainerProvider()))->toBeInstanceOf(HttpTransport::class);
    $client = new TestHttpClient();
    $transport = (new DefaultTransportFactory())->create(new MapContainerProvider([ClientInterface::class => $client]));
    expect($transport->send(new PreparedRequest(HttpMethod::GET, 'https://fixture.test'))->body)->toBe('payload');
});

it('не скрывает неверный binding за fallback и сохраняет локализацию', function (string $id, string $key, array $expected): void {
    $provider = new MapContainerProvider([
        ClientInterface::class => new TestHttpClient(),
        RequestFactoryInterface::class => new HttpFactory(),
        StreamFactoryInterface::class => new HttpFactory(),
        $id => new stdClass(),
    ]);
    try {
        (new DefaultTransportFactory())->create($provider);
        test()->fail('Неверный binding принят');
    } catch (ConfigurationException $error) {
        expect($provider->resolved)->toBe($expected);
        $ru = $error->localized(new LocalizationConfig('ru'));
        expect($error->getMessage())->not->toBe($key)
            ->and($ru->getMessage())->not->toBe($key)->not->toBe($error->getMessage())
            ->and($error->localized(new LocalizationConfig('en', messages: ['en' => [$key => 'Custom binding error']])))->getMessage()->toBe('Custom binding error')
            ->and($ru->localized(new LocalizationConfig('en'))->getMessage())->toBe($error->getMessage());
    }
})->with([
    [ClientInterface::class, 'transport.psr_18_client_binding_must_implement_clientinterface', [ClientInterface::class]],
    [RequestFactoryInterface::class, 'transport.psr_17_request_factory_binding_has_an_invalid_type', [ClientInterface::class, RequestFactoryInterface::class]],
    [StreamFactoryInterface::class, 'transport.psr_17_stream_factory_binding_has_an_invalid_type', [ClientInterface::class, RequestFactoryInterface::class, StreamFactoryInterface::class]],
]);
