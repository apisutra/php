<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\RateLimiting\BackoffStrategy;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Pipeline\Transport\RetryConfigResolver;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\RetryableRequest;
use ApiSutra\Tests\Stubs\Requests\RetryDeniedRequest;
use ApiSutra\Tests\Stubs\Requests\RetryDisabledRequest;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Retry\RetryDelayOverride;

it('разрешает частичную задержку поверх атрибута или клиента без потери остальных полей', function (bool $attribute): void {
    $base = new RetryConfig(attempts: 4, baseDelay: 300, maxDelay: 900, jitter: true,
        retryOn: [502], retryExceptions: [LogicException::class], totalTimeoutMs: 5000, safeMethods: [HttpMethod::POST]);
    $resolver = new RetryConfigResolver(new ClientConfig(baseUrl: 'https://fixture.test', retry: $base));
    $request = $attribute ? new RetryableRequest('fixture') : new RetryPolicyRequest();
    $retry = $resolver->resolve($request, RequestOptions::empty()->withRetryDelay(jitter: false));
    expect($retry->baseDelay)->toBe($attribute ? 10 : 300)
        ->and($retry->maxDelay)->toBe($attribute ? 50 : 900)
        ->and($retry->backoff)->toBe($attribute ? BackoffStrategy::Constant : BackoffStrategy::Exponential)
        ->and($retry->jitter)->toBeFalse()
        ->and($retry->attempts)->toBe($attribute ? 2 : 4)
        ->and($retry->retryOn)->toBe($attribute ? [429, 500, 502, 503, 504] : [502])
        ->and($retry->retryExceptions)->toBe($base->retryExceptions)
        ->and($retry->totalTimeoutMs)->toBe(5000)->and($retry->safeMethods)->toBe([HttpMethod::POST])
        ->and($base->jitter)->toBeTrue();
    $zero = $resolver->resolve($request, RequestOptions::empty()->withRetry(6)
        ->withRetryDelay(baseDelay: 0, maxDelay: 0, backoff: BackoffStrategy::Linear, jitter: false));
    expect([$zero->attempts, $zero->baseDelay, $zero->maxDelay, $zero->backoff, $zero->jitter])
        ->toBe([6, 0, 0, BackoffStrategy::Linear, false]);
})->with([false, true]);

it('выбирает весь runtime источник один раз, включая отсутствие и очистку delay', function (): void {
    $request = new class extends RetryPolicyRequest {
        #[Override]
        public function getRetryOverride(): array
        {
            return ['enabled' => true, 'attempts' => 9];
        }

        #[Override]
        public function getRetryDelayOverride(): ?RetryDelayOverride
        {
            return new RetryDelayOverride(baseDelay: 7, maxDelay: 8, jitter: false);
        }
    };
    $resolver = new RetryConfigResolver(new ClientConfig(baseUrl: 'https://fixture.test',
        retry: new RetryConfig(attempts: 3, baseDelay: 100, maxDelay: 500, jitter: true)));
    $cases = [
        [null, [9, 7, 8, false]],
        [RequestOptions::empty(), [3, 100, 500, true]],
        [RequestOptions::empty()->withRetryDelay(jitter: false), [3, 100, 500, false]],
        [RequestOptions::empty()->withRetryDelay(baseDelay: 1)->withoutRetryDelay()->withRetry(2), [2, 100, 500, true]],
    ];
    foreach ($cases as [$options, $expected]) {
        $retry = $resolver->resolve($request, $options);
        expect([$retry->attempts, $retry->baseDelay, $retry->maxDelay, $retry->jitter])->toBe($expected);
    }
});

it('заменяет и снимает delay независимо от enabled и attempts, не мутируя исходные снимки', function (): void {
    $request = new RetryPolicyRequest();
    $first = $request->withRetryDelay(baseDelay: 7, maxDelay: 8);
    $second = $first->withRetry(4)->withoutRetry()->withRetryDelay(jitter: false);
    $enabled = $second->withRetry(2);
    $cleared = $enabled->withoutRetryDelay()->withHeader('X-Fixture', 'yes')->withRetry(3);
    expect($request->getRetryDelayOverride())->toBeNull()
        ->and($first->getOptions()->getRetryDelayOverride()->baseDelay)->toBe(7)
        ->and($first->getOptions()->getRetryOverride())->toBe(['enabled' => null, 'attempts' => null])
        ->and($second->getOptions()->getRetryDelayOverride()->baseDelay)->toBeNull()
        ->and($second->getOptions()->getRetryDelayOverride()->maxDelay)->toBeNull()
        ->and($second->getOptions()->getRetryOverride())->toBe(['enabled' => false, 'attempts' => 4])
        ->and($enabled->getOptions()->getRetryDelayOverride())->toBe($second->getOptions()->getRetryDelayOverride())
        ->and($cleared->getOptions()->getRetryDelayOverride())->toBeNull()
        ->and($cleared->getOptions()->getRetryOverride())->toBe(['enabled' => true, 'attempts' => 3]);
});

it('delay не включает retry и не проверяет итоговую комбинацию выключенного retry', function (): void {
    $resolver = new RetryConfigResolver(new ClientConfig(baseUrl: 'https://fixture.test'));
    $options = RequestOptions::empty()->withRetryDelay(baseDelay: 20000);
    expect($resolver->resolve(new RetryPolicyRequest(), $options))->toBeNull()
        ->and($resolver->resolve(new RetryDisabledRequest(), $options))->toBeNull()
        ->and($resolver->resolve(new RetryPolicyRequest(), $options->withRetry(2)->withoutRetry()))->toBeNull();
    expect(fn () => $resolver->resolve(new RetryPolicyRequest(), $options->withRetry(2)))
        ->toThrow(ConfigurationException::class);
});

it('отклоняет пустой override и отрицательные значения непосредственно из setter', function (array $values): void {
    expect(fn () => RequestOptions::empty()->withRetryDelay(...$values))->toThrow(ConfigurationException::class)
        ->and(fn () => (new RetryPolicyRequest())->withoutRetry()->withRetryDelay(...$values))->toThrow(ConfigurationException::class);
})->with([[[]], [[null, null, null, null]], [[-1]], [[null, -1]]]);

it('отклоняет итоговую комбинацию при исполнении до HTTP исходного запроса', function (bool $throw): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', throwOnErrors: $throw,
        retry: new RetryConfig(baseDelay: 100, maxDelay: 500)), $transport);
    $execution = (new RetryPolicyRequest())->setClient($client)->withRetryDelay(baseDelay: 501);
    if ($throw) {
        expect(fn () => $execution->send())->toThrow(ConfigurationException::class);
    } else {
        expect($execution->send()->raw()->errors->first()->code->value)->toBe('configuration_error');
    }
    expect($transport->getRecorded())->toBe([]);
})->with([false, true]);

it('применяет задержку на одно исполнение и не подтверждает безопасность POST', function (bool $safe): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::sequence([MockResponse::serverError(), MockResponse::success()])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test',
        retry: new RetryConfig(attempts: 2, baseDelay: 100, jitter: true)), $transport, $clock, $clock);
    $request = (new RetryPolicyRequest($safe ? HttpMethod::GET : HttpMethod::POST))->setClient($client);
    $result = $request->withRetryDelay(baseDelay: 17, maxDelay: 17, backoff: BackoffStrategy::Constant, jitter: false)->send()->raw();
    expect($result->isSuccess())->toBe($safe)->and($clock->waits)->toBe($safe ? [17] : [])
        ->and($transport->getRecorded())->toHaveCount($safe ? 2 : 1)
        ->and($request->getRetryDelayOverride())->toBeNull();
})->with([false, true]);

it('не обходит safe false при runtime задержке', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::serverError()]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport);
    $result = (new RetryDeniedRequest())->setClient($client)->withRetryDelay(baseDelay: 0, jitter: false)->send()->raw();
    expect($result->errors->first()->context['retryRefusalReason'])->toBe('operation_not_safe')
        ->and($transport->getRecorded())->toHaveCount(1);
});

it('не сокращает Retry-After и отказывает до сна при нехватке бюджета', function (bool $budget): void {
    $clock = new VirtualClock();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::sequence([
        MockResponse::make('fixture', 503, ['Retry-After' => '2']), MockResponse::success(),
    ])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test',
        retry: new RetryConfig(attempts: 2, totalTimeoutMs: $budget ? 1000 : null)), $transport, $clock, $clock);
    $result = (new RetryPolicyRequest())->setClient($client)->withRetryDelay(baseDelay: 0, maxDelay: 0, jitter: false)->send()->raw();
    expect($result->isSuccess())->toBe(!$budget)->and($clock->waits)->toBe($budget ? [] : [2000])
        ->and($transport->getRecorded())->toHaveCount($budget ? 1 : 2);
    if ($budget) {
        expect($result->errors->first()->context['reason'])->toBe('execution_deadline_exceeded');
    }
})->with([false, true]);
