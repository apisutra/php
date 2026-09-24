<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CooldownConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Testing\UnmockedRequestException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\Pagination\PageRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use ApiSutra\Timing\CooperativeSleeper;
use ApiSutra\Transport\MockTransport;

it('сохраняет нарушение fake при смене ответов и восстанавливает исходный транспорт', function (bool $async): void {
    $transport = new MockTransport();
    $transport->fake([SimpleGetRequest::class => MockResponse::success(['id' => 9, 'name' => 'original'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://example.test'), $transport);
    $session = $client->beginFakeSession()->fake([]);
    $request = new SimpleGetRequest('q');
    $result = $async ? $client->sendAsync($request)->wait() : $client->send($request);
    expect($result->raw()->isFailed())->toBeTrue();
    $session->fake([SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'mock'])]);
    $session->assertNothingSent();
    expect(fn () => $session->verify())->toThrow(UnmockedRequestException::class);
    $session->close();
    $session->close();
    expect($client->send($request)->dataOrFail()->id)->toBe(9);
    $transport->assertSent(SimpleGetRequest::class, times: 1);
    expect(fn () => $session->fake([]))->toThrow(ConfigurationException::class);
})->with([false, true]);

it('не заменяет активный транспорт и разрешает замену после завершения', function (): void {
    $client = new TestClient(new ClientConfig(baseUrl: 'https://example.test'), new MockTransport());
    $session = $client->beginFakeSession()->fake([SimpleGetRequest::class => static function (): MockResponse {
        (new CooperativeSleeper())->sleepMs(20);
        return MockResponse::success(['id' => 1, 'name' => 'mock']);
    }]);
    $pending = $client->sendAsync(new SimpleGetRequest('q'));
    expect(fn () => $session->fake([]))->toThrow(ConfigurationException::class)
        ->and(fn () => $session->close())->toThrow(ConfigurationException::class);
    $pending->wait();
    $session->fake([])->assertNothingSent();
    $session->close();
});

it('строгость сессии не зависит от фабрики и режима исключений', function (bool $throw, string $factoryMode): void {
    $factory = new RecordingFactory(fallback: $factoryMode === 'null', failure: $factoryMode === 'throws' ? new RuntimeException('factory') : null);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://example.test', throwOnErrors: $throw, resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory)), new MockTransport());
    $session = $client->beginFakeSession()->fake([]);
    try {
        $client->send(new SimpleGetRequest('q'))->raw();
    } catch (Throwable) {
        // Даже намеренно проглоченная приложением ошибка не отменяет проверку теста.
    }
    expect(fn () => $session->verify())->toThrow(UnmockedRequestException::class);
    $session->close();
})->with([false, true])->with(['returns', 'null', 'throws']);

it('сохраняет локальный cooldown при fake и восстановлении', function (): void {
    $original = new MockTransport();
    $original->fake([SimpleGetRequest::class => MockResponse::rateLimited(30)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://example.test', retry: new RetryConfig(attempts: 1), cooldown: new CooldownConfig(maxAdditionalWaitMs: 0)), $original);
    $client->send(new SimpleGetRequest('q'))->raw();
    $session = $client->beginFakeSession()->fake([SimpleGetRequest::class => MockResponse::success([])]);
    $result = $client->send(new SimpleGetRequest('q'))->raw();
    expect($result->isFailed())->toBeTrue();
    $session->assertNothingSent();
    $session->close();
    expect($client->send(new SimpleGetRequest('q'))->raw()->isFailed())->toBeTrue();
    $original->assertSent(SimpleGetRequest::class, times: 1);
});

it('не меняет транспорт между страницами удержанного ленивого обхода', function (): void {
    $client = new TestClient(new ClientConfig(baseUrl: 'https://example.test'), new MockTransport());
    $session = $client->beginFakeSession()->fake([PageRequest::class => MockResponse::success([
        'data' => [1], 'meta' => ['page' => 1, 'has_more' => true],
    ])]);
    $items = (new PageRequest())->setClient($client)->paginate()->items();
    $items->rewind();
    expect($items->current())->toBe(1)
        ->and(fn () => $session->fake([]))->toThrow(ConfigurationException::class);
    unset($items);
    $session->close();
    $client->beginFakeSession()->close();
});
