<?php

declare(strict_types=1);

use ApiSutra\Auth\OAuth2\OAuth2Authenticator;
use ApiSutra\Auth\OAuth2\OAuth2Credential;
use ApiSutra\Auth\OAuth2\OAuth2TokenSet;
use ApiSutra\Config\OAuth2Config;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Tests\Support\StrictCache;
use ApiSutra\Exceptions\Testing\RecordingException;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\Config\CooldownConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Request\CooldownException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Auth\RecordingAuthenticator;
use ApiSutra\Tests\Stubs\RateLimit\CallbackHook;
use ApiSutra\Tests\Stubs\RateLimit\CooldownRequest;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Support\CooldownScenario;
use ApiSutra\Timing\ExecutionDeadline;
use ApiSutra\VO\Pipeline\PipelineContext;

it('использует auto с budget, запасной или явный предел, сохраняя полный запрет', function (?int $budget, ?int $cap, int $seconds, bool $success, string $error): void {
    $s = new CooldownScenario(new ClientConfig(
        baseUrl: 'https://fixture.test',
        retry: new RetryConfig(attempts: 1, totalTimeoutMs: $budget),
        cooldown: new CooldownConfig(maxAdditionalWaitMs: $cap)
    ), $seconds);
    $s->client->send(new RetryPolicyRequest());
    $result = $s->client->send(new RetryPolicyRequest())->raw();
    expect($result->isSuccess())->toBe($success)->and($s->clock->waits)->toBe($success ? [$seconds * 1000] : []);
    if (!$success) {
        expect($result->errors->first()->context['reason'])->toBe($error);
        if ($result->exception instanceof CooldownException) {
            expect($result->exception->retryAfterMs)->toBe($seconds * 1000);
        }
        expect($s->transport->getRecorded())->toHaveCount(1);
    }
})->with([
    'auto without deadline' => [null, null, 30, false, 'server_cooldown_active'],
    'auto boundary' => [null, null, 1, true, ''],
    'auto with budget' => [60_000, null, 30, true, ''],
    'explicit cap with budget' => [60_000, 1000, 30, false, 'server_cooldown_active'],
    'zero with budget' => [60_000, 0, 1, false, 'server_cooldown_active'],
    'deadline first' => [1000, 0, 30, false, 'execution_deadline_exceeded'],
    'explicit long wait without budget' => [null, 30_000, 30, true, ''],
]);

it('не начисляет собственный Retry-After в добавочный лимит и вычитает время hook', function (bool $date, bool $enabled): void {
    $s = new CooldownScenario(new ClientConfig(
        baseUrl: 'https://fixture.test',
        retry: new RetryConfig(attempts: 2, baseDelay: 2000, maxDelay: 2000, jitter: false),
        cooldown: new CooldownConfig(enabled: $enabled, maxAdditionalWaitMs: 0)
    ));
    $s->transport->fake(['*' => MockResponse::sequence([
        MockResponse::make('{}', 429, ['Retry-After' => $date ? gmdate('D, d M Y H:i:s \G\M\T', $s->clock->unixTime() + 30) : '30']),
        MockResponse::success(),
    ])]);
    $s->client->hooks()->on(Hook::AfterResponse, new CallbackHook(static function (PipelineContext $context) use ($s): void {
        if ($context->response->status === 429) {
            $s->clock->advance(10_000);
        }
    }));
    expect($s->client->send(new RetryPolicyRequest())->raw()->isSuccess())->toBeTrue()
        ->and($s->clock->waits)->toBe([20_000])->and($s->transport->getRecorded())->toHaveCount(2);
})->with([[false, false], [true, false], [false, true], [true, true]]);

it('публикует 429 до упавшего hook и не разрешает повтор небезопасного POST', function (): void {
    $s = new CooldownScenario(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig()));
    $s->client->hooks()->on(Hook::AfterResponse, new CallbackHook(static function (): void {
        throw new RuntimeException('fixture');
    }));
    $s->client->send(new RetryPolicyRequest(HttpMethod::POST));
    $s->client->hooks()->remove(Hook::AfterResponse);
    $result = $s->client->send(new RetryPolicyRequest(HttpMethod::POST))->raw();
    expect($result->exception)->toBeInstanceOf(CooldownException::class)->and($s->transport->getRecorded())->toHaveCount(1)
        ->and($s->clock->waits)->toBe([]);
    $events = array_filter($result->audit, static fn ($event): bool => $event->stage === PipelineStage::HttpRequest);
    expect($events)->toBe([]);
});

it('сохраняет конфиг и runtime в цепочках, наследует атрибут и возвращает auto полным override', function (): void {
    $rule = new CooldownConfig(group: 'reports', maxAdditionalWaitMs: 0);
    $config = (new ClientConfig(baseUrl: 'https://fixture.test', cooldown: $rule))->with(debug: true);
    expect($config->cooldown)->toBe($rule);
    $options = RequestOptions::empty()->withCooldown($rule)->withTimeout(10)->withoutRetry();
    expect($options->getCooldownOverride())->toBe($rule);
    $s = new CooldownScenario($config, 2);
    $request = new CooldownRequest();
    $s->client->send($request);
    expect($s->client->send($request)->raw()->isSuccess())->toBeTrue()->and($s->clock->waits)->toBe([2000]);
    $s = new CooldownScenario($config, 1);
    $s->client->send(new RetryPolicyRequest());
    expect($s->client->send((new RetryPolicyRequest())->withCooldown(new CooldownConfig(group: 'reports')))->raw()->isSuccess())->toBeTrue();
});

it('изолирует классы, origin, фактические credentials и explicit identity', function (string $change): void {
    $s = new CooldownScenario();
    $first = (new RetryPolicyRequest())->withHeader('Authorization', 'Bearer first');
    $s->client->send($first);
    $next = match ($change) {
        'class' => (new CooldownRequest())->withHeader('Authorization', 'Bearer first'),
        'origin' => (new RetryPolicyRequest())->withBaseUrl('https://other.test')->withHeader('Authorization', 'Bearer first'),
        'credential' => (new RetryPolicyRequest())->withHeader('Authorization', 'Bearer second'),
        'identity' => $first->withCooldown(new CooldownConfig(identity: 'other')),
    };
    expect($s->client->send($next)->raw()->isSuccess())->toBeTrue()->and($s->clock->waits)->toBe([]);
})->with(['class', 'origin', 'credential', 'identity']);

it('объединяет классы явной группой и умеет отключаться на один вызов', function (): void {
    $s = new CooldownScenario();
    $s->client->send((new RetryPolicyRequest())->withCooldown(new CooldownConfig(group: 'reports')));
    expect($s->client->send(new CooldownRequest())->raw()->exception)->toBeInstanceOf(CooldownException::class);
    expect($s->client->send((new CooldownRequest())->withCooldown(new CooldownConfig(enabled: false)))->raw()->isSuccess())->toBeTrue();
});

it('не объединяет неизвестный custom auth без явной identity', function (bool $explicit): void {
    $s = new CooldownScenario(new ClientConfig(
        baseUrl: 'https://fixture.test',
        auth: new RecordingAuthenticator(),
        cooldown: new CooldownConfig(identity: $explicit ? 'connection' : null)
    ));
    $s->client->send(new RetryPolicyRequest());
    expect($s->client->send(new RetryPolicyRequest())->raw()->isSuccess())->toBe(!$explicit);
})->with([false, true]);

it('Throw отказывает до request delay, а withTimeout не заменяет общий budget', function (bool $throw): void {
    $s = new CooldownScenario(new ClientConfig(baseUrl: 'https://fixture.test', cooldown: new CooldownConfig(
        behavior: $throw ? RateLimitBehavior::Throw : RateLimitBehavior::Wait
    )));
    $s->client->send(new RetryPolicyRequest());
    $request = (new RetryPolicyRequest())->withTimeout(60);
    if ($throw) {
        $request = $request->withDelay(5000);
    }
    expect($s->client->send($request)->raw()->exception)->toBeInstanceOf(CooldownException::class)->and($s->clock->waits)->toBe([]);
})->with([false, true]);

it('внешний deadline разрешает ожидание и ограничивает цепочку независимых вызовов', function (): void {
    $s = new CooldownScenario();
    $s->client->send(new RetryPolicyRequest());
    $deadline = ExecutionDeadline::afterMs(31_000, $s->clock);
    expect($s->client->send((new RetryPolicyRequest())->withDeadline($deadline))->raw()->isSuccess())->toBeTrue();
    $s->clock->advance(1000);
    expect($s->client->send((new RetryPolicyRequest())->withDeadline($deadline))->raw()->exception)->toBeInstanceOf(ExecutionDeadlineException::class)
        ->and($s->transport->getRecorded())->toHaveCount(2);
});

it('не создаёт cooldown по невалидному заголовку или 503', function (int $status, ?string $header): void {
    $s = new CooldownScenario();
    $s->transport->fake(['*' => MockResponse::sequence([MockResponse::make('{}', $status, $header === null ? [] : ['Retry-After' => $header]), MockResponse::success()])]);
    $s->client->send(new RetryPolicyRequest());
    expect($s->client->send(new RetryPolicyRequest())->raw()->isSuccess())->toBeTrue()->and($s->clock->waits)->toBe([]);
})->with([[429, null], [429, '0'], [429, '-1'], [429, 'invalid'], [429, '99999999999999999999'], [503, '30']]);

it('сохраняет состояние при fake rebuild и изолирует другой клиент', function (): void {
    $s = new CooldownScenario();
    $s->client->send(new RetryPolicyRequest());
    $s->client->fake(['*' => MockResponse::success()]);
    expect($s->client->send(new RetryPolicyRequest())->raw()->exception)->toBeInstanceOf(CooldownException::class);
    $other = new CooldownScenario();
    $other->transport->fake(['*' => MockResponse::success()]);
    expect($other->client->send(new RetryPolicyRequest())->raw()->isSuccess())->toBeTrue();
});

it('отклоняет неправильный конфиг без раскрытия identity', function (): void {
    expect(fn () => new CooldownConfig(group: ' '))->toThrow(ConfigurationException::class)
        ->and(fn () => new CooldownConfig(identity: ''))->toThrow(ConfigurationException::class)
        ->and(fn () => new CooldownConfig(maxAdditionalWaitMs: -1))->toThrow(ConfigurationException::class);
});

it('учитывает фактический ответ при сбое записи и истечении HTTP budget', function (bool $recording): void {
    $s = new CooldownScenario(new ClientConfig(baseUrl: 'https://fixture.test', retry: new RetryConfig(attempts: 1, totalTimeoutMs: 1000)));
    $s->transport->fake(['*' => static function () use ($s, $recording): MockResponse {
        if ($recording) {
            $prepared = new PreparedRequest(HttpMethod::GET, 'https://fixture.test/retry-policy');
            throw new RecordingException(MockResponse::rateLimited(30)->toProviderResponse($prepared), new RuntimeException('fixture recording failure'));
        }
        $s->clock->advance(1000);
        return MockResponse::rateLimited(30);
    }]);
    $s->client->send(new RetryPolicyRequest());
    $result = $s->client->send((new RetryPolicyRequest())->withCooldown(new CooldownConfig(behavior: RateLimitBehavior::Throw)))->raw();
    expect($result->exception)->toBeInstanceOf(CooldownException::class)->and($result->response)->toBeNull()
        ->and($s->transport->getRecorded())->toHaveCount(1);
})->with([false, true]);

it('cache hit не ждёт запрета и не создаёт новый срок', function (): void {
    $s = new CooldownScenario(new ClientConfig(baseUrl: 'https://fixture.test', cacheConfig: new CacheConfig(store: new StrictCache())));
    $s->transport->fake(['*' => MockResponse::sequence([MockResponse::success(['value' => 1]), MockResponse::rateLimited(30)])]);
    $s->client->send((new RetryPolicyRequest())->withCache());
    $s->client->send((new RetryPolicyRequest())->withoutCache());
    expect($s->client->send((new RetryPolicyRequest())->withCache())->raw()->data)->toBe(['value' => 1])
        ->and($s->clock->waits)->toBe([])->and($s->transport->getRecorded())->toHaveCount(2);
});


it('сохраняет запрет при настоящем OAuth refresh, но различает замену credential в hook', function (): void {
    $credential = new OAuth2Credential(new OAuth2TokenSet('first', 'refresh', 1_800_000_010, ['a', 'b']));
    $s = new CooldownScenario(new ClientConfig(
        baseUrl: 'https://fixture.test',
        auth: OAuth2Authenticator::authorizationCode(new OAuth2Config('https://id.test/token', 'client', 'secret'), $credential)
    ));
    $s->client->send(new RetryPolicyRequest());
    $s->clock->advance(11_000);
    $s->transport->fake([
        'https://id.test/token' => MockResponse::success(['access_token' => 'second', 'token_type' => 'Bearer', 'expires_in' => 3600]),
        '*' => MockResponse::success(),
    ]);
    $result = $s->client->send(new RetryPolicyRequest())->raw();
    expect($credential->tokens()->accessToken)->toBe('second')
        ->and($credential->tokens()->scopes)->toBe(['a', 'b'])
        ->and($result->exception)->toBeInstanceOf(CooldownException::class)
        ->and($result->exception->retryAfterMs)->toBe(19_000)
        ->and($s->transport->getRecorded())->toHaveCount(2);
    $s->client->hooks()->on(Hook::BeforeSend, new CallbackHook(static function (PipelineContext $context): void {
        $context->preparedRequest = $context->preparedRequest->with(headers: ['Authorization' => 'Bearer other-credential']);
    }));
    expect($s->client->send(new RetryPolicyRequest())->raw()->isSuccess())->toBeTrue()
        ->and($s->transport->getRecorded())->toHaveCount(3)->and($s->clock->waits)->toBe([]);
});
