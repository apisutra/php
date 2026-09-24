<?php

declare(strict_types=1);

use ApiSutra\Auth\OAuth2\Internal\TokenRequest;
use ApiSutra\Auth\OAuth2\OAuth2Authenticator;
use ApiSutra\Auth\OAuth2\OAuth2Credential;
use ApiSutra\Auth\OAuth2\OAuth2TokenSet;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\OAuth2Config;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\OAuth2\ResourceRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use GuzzleHttp\Promise\CancellationException;
use GuzzleHttp\Promise\Utils;
use Revolt\EventLoop;

it('один credential двух клиентов обновляется один раз и сохраняет trace зависимости', function (): void {
    $watchers = EventLoop::getIdentifiers();
    $credential = new OAuth2Credential(new OAuth2TokenSet('old', 'rotating', 1));
    $auth = OAuth2Authenticator::authorizationCode(new OAuth2Config('https://id.test/token', 'id', 'secret'), $credential);
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => static function (): MockResponse {
        AsyncTask::current()->runtime->sleep(15);
        return MockResponse::success(['access_token' => 'new', 'refresh_token' => 'next', 'token_type' => 'Bearer']);
    }, ResourceRequest::class => MockResponse::success()]);
    $a = new TestClient(new ClientConfig(baseUrl: 'https://first-api.test', auth: $auth), $transport);
    $b = new TestClient(new ClientConfig(baseUrl: 'https://second-api.test', auth: $auth), $transport);
    $handles = Utils::all([$a->sendAsync((new ResourceRequest())->withTraceId('first')), $b->sendAsync((new ResourceRequest())->withTraceId('second'))])->wait();
    foreach ($handles as $handle) {
        expect($handle->raw()->isSuccess())->toBeTrue();
    }
    $first = $handles[0]->raw();
    expect($transport->getRecorded())->toHaveCount(3)->and($first->nested)->toHaveCount(1)
        ->and($first->nested[0]->trace->parentExecutionId)->toBe($first->trace->executionId)
        ->and($first->nested[0]->traceId)->toBe('first')->and($credential->tokens()->refreshToken)->toBe('next');
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe($watchers);
});

it('поздний 401 сравнивается с версией своей попытки и не запускает вторую ротацию', function (bool $withRetry): void {
    $credential = new OAuth2Credential(new OAuth2TokenSet('old', 'rotating'));
    $transport = new MockTransport();
    $oldCalls = 0;
    $transport->fake([
        ResourceRequest::class => function ($request) use (&$oldCalls): MockResponse {
            if ($request->getContext()->preparedRequest->headers['Authorization'] === 'Bearer old') {
                $oldCalls++;
                AsyncTask::current()->runtime->sleep($oldCalls === 1 ? 5 : 35);
                return MockResponse::make('unauthorized', 401);
            }
            return MockResponse::success();
        },
        TokenRequest::class => MockResponse::success(['access_token' => 'new', 'refresh_token' => 'next', 'token_type' => 'Bearer']),
    ]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: OAuth2Authenticator::authorizationCode(new OAuth2Config('https://id.test/token', 'id', 'secret'), $credential), retry: $withRetry ? new RetryConfig(attempts: 2, baseDelay: 0) : null), $transport);
    $results = Utils::all([$client->sendAsync(new ResourceRequest()), $client->sendAsync(new ResourceRequest())])->wait();
    expect($results[0]->raw()->isSuccess())->toBeTrue()->and($results[1]->raw()->isSuccess())->toBeTrue();
    $transport->assertSent(TokenRequest::class, times: 1);
    expect($transport->getRecorded())->toHaveCount(5);
})->with([false, true]);

it('отмена ожидающего не отменяет владельца, отмена владельца запрещает старый refresh', function (bool $cancelOwner): void {
    $watchers = EventLoop::getIdentifiers();
    $credential = new OAuth2Credential(new OAuth2TokenSet('old', 'rotating', 1));
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => static function (): MockResponse {
        AsyncTask::current()->runtime->sleep(60);
        return MockResponse::success(['access_token' => 'new', 'token_type' => 'Bearer']);
    }, ResourceRequest::class => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: OAuth2Authenticator::authorizationCode(new OAuth2Config('https://id.test/token', 'id', 'secret'), $credential)), $transport);
    $owner = $client->sendAsync(new ResourceRequest());
    $waiter = $client->sendAsync(new ResourceRequest());
    $cancelled = $cancelOwner ? $owner : $waiter;
    $survivor = $cancelOwner ? $waiter : $owner;
    EventLoop::delay(0.01, $cancelled->cancel(...));
    expect(fn () => $cancelled->wait())->toThrow(CancellationException::class);
    $result = $survivor->wait()->raw();
    expect($result->isSuccess())->toBe(!$cancelOwner)->and($credential->isRefreshing())->toBeFalse();
    if ($cancelOwner) {
        expect($result->errors->first()->context['reason'])->toBe('oauth2_refresh_outcome_unknown');
        expect($client->send(new ResourceRequest())->raw()->isFailed())->toBeTrue();
    }
    $transport->assertSent(TokenRequest::class, times: 1);
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe($watchers);
})->with([false, true]);

it('deadline ожидающего не отбирает владение refresh', function (): void {
    $credential = new OAuth2Credential(new OAuth2TokenSet('old', 'rotating', 1));
    $auth = OAuth2Authenticator::authorizationCode(new OAuth2Config('https://id.test/token', 'id', 'secret'), $credential);
    $transport = new MockTransport();
    $transport->fake([TokenRequest::class => static function (): MockResponse {
        AsyncTask::current()->runtime->sleep(65);
        return MockResponse::success(['access_token' => 'new', 'token_type' => 'Bearer']);
    }, ResourceRequest::class => MockResponse::success()]);
    $a = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: $auth), $transport);
    $b = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: $auth, retry: new RetryConfig(totalTimeoutMs: 25)), $transport);
    $owner = $a->sendAsync(new ResourceRequest());
    $waiter = $b->sendAsync(new ResourceRequest());
    expect($waiter->wait()->raw()->errors->first()->context['reason'])->toBe('execution_deadline_exceeded')
        ->and($owner->wait()->raw()->isSuccess())->toBeTrue();
    $transport->assertSent(TokenRequest::class, times: 1);
});

it('ожидает завершение кооперативного сохранения перед применением новой пары', function (): void {
    $saved = false;
    $credential = new OAuth2Credential(new OAuth2TokenSet('old', 'r', 1), onTokensChanged: function () use (&$saved): void {
        AsyncTask::current()->runtime->sleep(15);
        $saved = true;
    });
    $transport = new MockTransport();
    $transport->fake([
        TokenRequest::class => MockResponse::success(['access_token' => 'new', 'token_type' => 'Bearer']),
        ResourceRequest::class => function () use (&$saved): MockResponse {
            expect($saved)->toBeTrue();
            return MockResponse::success();
        },
    ]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', auth: OAuth2Authenticator::authorizationCode(new OAuth2Config('https://id.test/token', 'id', 'secret'), $credential)), $transport);
    $results = Utils::all([$client->sendAsync(new ResourceRequest()), $client->sendAsync(new ResourceRequest())])->wait();
    expect($results[0]->raw()->isSuccess())->toBeTrue()->and($results[1]->raw()->isSuccess())->toBeTrue();
    $transport->assertSent(TokenRequest::class, times: 1);
});

it('TTL не передаёт локальное владение незавершённым refresh', function (): void {
    $credential = new OAuth2Credential(new OAuth2TokenSet('old', 'r'));
    $locks = $credential->refreshLockProvider();
    $lease = $locks->acquire('first-api', 0);
    expect($lease)->not->toBeNull()->and($locks->acquire('second-api', 0))->toBeNull();
    expect($lease->release())->toBeTrue()->and($lease->release())->toBeFalse()
        ->and($locks->acquire('second-api', 0))->not->toBeNull();
});
