<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Core\RuntimeException;
use ApiSutra\Exceptions\Testing\MissingFixtureException;
use ApiSutra\Exceptions\Testing\UnmockedRequestException;
use ApiSutra\Testing\MockClient;
use ApiSutra\Testing\MockConfig;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\ProtectedRequest;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\Retry\ProbeTransportDecorator;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

describe('Transport testing', function () {
    afterEach(function (): void {
        MockClient::destroyGlobal();
    });

    it('MockClient::global перехватывает запросы', function () {
        MockClient::destroyGlobal();
        MockClient::global([
            SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'global']),
        ]);

        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 2, 'name' => 'local']),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );

        $request = new SimpleGetRequest('q');
        $request->setClient($client);
        $result = $request->send()->raw();

        expect($result->data->name ?? null)->toBe('global');
    });

    it('record и playback используют фикстуры', function () {
        $path = sys_get_temp_dir() . '/apisutra-fixtures-' . uniqid();

        $transport = new MockTransport();
        $transport->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 10, 'name' => 'recorded']),
        ]);

        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
        );
        $client->record($path);
        expect(fn () => $client->assertNothingSent())->toThrow(ConfigurationException::class)
            ->and(fn () => $client->preventStrayRequests())->toThrow(ConfigurationException::class);

        $request = new SimpleGetRequest('q');
        $request->setClient($client);
        $request->send()->raw();

        $files = glob($path . '/*.json') ?: [];
        expect($files)->not->toBeEmpty();

        $playbackClient = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            new MockTransport(),
        );
        $playbackClient->playback($path);
        $playbackClient->preventStrayRequests();
        $playbackClient->assertNothingSent();

        $playbackRequest = new SimpleGetRequest('q');
        $playbackRequest->setClient($playbackClient);
        $result = $playbackRequest->send()->raw();

        expect($result->data->name ?? null)->toBe('recorded');
        $playbackClient->assertSent(SimpleGetRequest::class, times: 1);

        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($path);
    });

    it('client->fake и assertSent работают', function () {
        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            new MockTransport(),
        );
        $client->fake([
            SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'ok']),
        ]);

        $request = new SimpleGetRequest('q');
        $request->setClient($client);
        $result = $request->send()->raw();

        $client->assertSent(SimpleGetRequest::class, null, 1);
        $client->assertNotSent(ProtectedRequest::class);
        expect($result->data->id ?? null)->toBe(1);
    });

    it('явно отклоняет тестовые проверки без mock до и после отправки', function (Closure $check, bool $sent): void {
        $inner = new MockTransport();
        $inner->fake([SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'ok'])]);
        $transport = new ProbeTransportDecorator($inner);
        $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $transport);

        if ($sent) {
            expect($client->send(new SimpleGetRequest('q'))->raw()->isSuccess())->toBeTrue();
        }

        expect(fn () => $check($client))->toThrow(ConfigurationException::class, 'call fake() or playback() first');
        expect($transport->entries)->toHaveCount($sent ? 1 : 0);

        // Ошибка настройки не заменяет транспорт пустым fake и не стирает его историю.
        expect($client->send(new SimpleGetRequest('q'))->raw()->isSuccess())->toBeTrue();
        expect($transport->entries)->toHaveCount($sent ? 2 : 1);
    })->with([
        'assertSent' => [static fn (TestClient $client) => $client->assertSent(SimpleGetRequest::class, times: 99)],
        'assertNotSent' => [static fn (TestClient $client) => $client->assertNotSent(SimpleGetRequest::class)],
        'assertNothingSent' => [static fn (TestClient $client) => $client->assertNothingSent()],
        'preventStrayRequests' => [static fn (TestClient $client) => $client->preventStrayRequests()],
    ])->with([false, true]);

    it('локализует ошибку отсутствующего mock настройками клиента', function (): void {
        $client = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', localization: new LocalizationConfig('ru')),
            new ProbeTransportDecorator(new MockTransport()),
        );

        expect(fn () => $client->assertNothingSent())
            ->toThrow(ConfigurationException::class, 'Требуется активный MockTransport');
    });

    it('после fake защищает sync и async и проверяет реальную историю попыток', function (bool $async): void {
        $original = new ProbeTransportDecorator(new MockTransport());
        $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test'), $original);
        $client->fake([]);
        $client->preventStrayRequests();
        $client->assertNothingSent();

        $request = new SimpleGetRequest('q');
        $failure = ($async ? $client->sendAsync($request)->wait() : $client->send($request))->raw();
        expect($failure->isFailed())->toBeTrue()
            ->and($failure->exception)->toBeInstanceOf(UnmockedRequestException::class);
        $client->assertSent(SimpleGetRequest::class, times: 1);

        $client->fake([SimpleGetRequest::class => MockResponse::success(['id' => 7, 'name' => 'fake'])]);
        $result = ($async ? $client->sendAsync($request)->wait() : $client->send($request))->raw();
        expect($result->data->id)->toBe(7)->and($original->entries)->toBe([]);
        $client->assertSent(SimpleGetRequest::class, times: 2);
        $client->assertNotSent(ProtectedRequest::class);
        expect(fn () => $client->assertNothingSent())->toThrow(RuntimeException::class)
            ->and(fn () => $client->assertNotSent(SimpleGetRequest::class))->toThrow(RuntimeException::class)
            ->and(fn () => $client->assertSent(SimpleGetRequest::class, times: 99))->toThrow(RuntimeException::class);
    })->with([false, true]);

    it('выбрасывает MissingFixtureException при отсутствии фикстуры', function () {
        $path = sys_get_temp_dir() . '/apisutra-missing-' . uniqid();
        @mkdir($path, 0777, true);

        MockConfig::setFixturePath($path);
        MockConfig::throwOnMissingFixtures();

        $transport = new MockTransport();
        $request = new SimpleGetRequest('q');
        $prepared = new \ApiSutra\VO\Http\PreparedRequest(
            method: ApiSutra\Enums\Http\HttpMethod::GET,
            url: 'https://api.test',
            meta: ['requestClass' => SimpleGetRequest::class],
        );

        expect(fn () => $transport->send($prepared))
            ->toThrow(MissingFixtureException::class);

        $reset = new ReflectionProperty(MockConfig::class, 'throwOnMissingFixtures');
        $reset->setValue(null, false);

        $fixturePath = new ReflectionProperty(MockConfig::class, 'fixturePath');
        $fixturePath->setValue(null, null);

        @rmdir($path);
    });
});
