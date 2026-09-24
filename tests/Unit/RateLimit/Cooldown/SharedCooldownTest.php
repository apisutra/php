<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Auth\OAuth2\OAuth2TokenSet;
use ApiSutra\Auth\OAuth2\OAuth2Credential;
use ApiSutra\Auth\OAuth2\OAuth2Authenticator;
use ApiSutra\Config\OAuth2Config;
use ApiSutra\Config\CooldownConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Http\TransmissionState;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\Enums\RateLimiting\RateLimitBehavior;
use ApiSutra\Exceptions\RateLimiting\CooldownBackendException;
use ApiSutra\Exceptions\Request\CooldownException;
use ApiSutra\Exceptions\Testing\RecordingException;
use ApiSutra\Execution\ExecutionErrorFactory;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Transport\CooldownCoordinator;
use ApiSutra\Pipeline\Transport\CooldownStore;
use ApiSutra\Pipeline\Transport\ResolvedCooldown;
use ApiSutra\RateLimiting\Cooldown\Backends\LocalCooldownBackend;
use ApiSutra\RateLimiting\Cooldown\CooldownUpdate;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\RateLimit\ScriptedCooldownBackend;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Auth\RefreshingAuthenticator;
use ApiSutra\Tests\Stubs\Tracing\MemoryLogger;
use ApiSutra\Tests\Support\CooldownScenario;
use ApiSutra\Tests\Support\VirtualClock;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LogLevel;

it('разделяет только явно общий backend, сохраняя его в with и при замене транспорта', function (): void {
    $backend = new LocalCooldownBackend();
    $config = new ClientConfig(baseUrl: 'https://fixture.test', cooldownBackend: $backend);
    expect($config->with(debug: true)->cooldownBackend)->toBe($backend)
        ->and($config->with(cooldownBackend: null)->cooldownBackend)->toBeNull();
    $a = new CooldownScenario($config);
    $b = new CooldownScenario($config);
    $a->client->send(new RetryPolicyRequest());
    expect($b->client->send(new RetryPolicyRequest())->raw()->exception)->toBeInstanceOf(CooldownException::class)
        ->and($b->transport->getRecorded())->toBe([]);
    $b->client->fake(['*' => MockResponse::success()]);
    expect($b->client->send(new RetryPolicyRequest())->raw()->exception)->toBeInstanceOf(CooldownException::class);
    $independent = new CooldownScenario($config->with(cooldownBackend: new LocalCooldownBackend()));
    expect($independent->client->send(new RetryPolicyRequest())->raw()->response->status)->toBe(429);
    $other = new CooldownScenario($config->with(baseUrl: 'https://other.test'));
    expect($other->client->send(new RetryPolicyRequest())->raw()->response->status)->toBe(429);
});

it('ошибка чтения или некорректный ответ останавливает HTTP и не раскрывает детали', function (bool $invalid, bool $async): void {
    $backend = new ScriptedCooldownBackend(static function () use ($invalid): int {
        if ($invalid) { return -1; }
        throw new RuntimeException('redis://password@secret-host/key-with-token');
    });
    $logger = new MemoryLogger();
    $s = new CooldownScenario(new ClientConfig(
        baseUrl: 'https://fixture.test', cooldownBackend: $backend, logger: $logger, debug: true,
        retry: new RetryConfig(attempts: 3, retryExceptions: [Throwable::class]),
    ));
    $request = new RetryPolicyRequest();
    $result = ($async ? $s->client->sendAsync($request)->wait() : $s->client->send($request))->raw();
    expect($result->errors->first()->code)->toBe(ErrorCode::ExecutionError)
        ->and($result->errors->first()->context)->toMatchArray(['reason' => 'cooldown_backend_error', 'stage' => 'cooldown_read', 'transmissionState' => TransmissionState::NotSent->value])
        ->and($s->transport->getRecorded())->toBe([])->and($backend->calls)->toHaveCount(1)
        ->and($result->response)->toBeNull()->and($result->debug->response)->toBeNull()
        ->and(json_encode($logger->records))->not->toContain('password', 'secret-host', 'key-with-token');
    $terminal = array_values(array_filter($logger->records, static fn (array $r): bool => ($r['context']['event'] ?? null) === 'failed'));
    expect($terminal)->toHaveCount(1)->and($terminal[0]['context'])->toMatchArray(['reason' => 'cooldown_backend_error', 'stage' => 'cooldown_read']);
})->with([[false, false], [true, false], [false, true]]);

it('сохраняет полученный 429 при сбое публикации без ordinary retry', function (bool $recording): void {
    $backend = new ScriptedCooldownBackend(static fn (): int => 0, static fn () => throw new RuntimeException('publish secret'));
    $s = new CooldownScenario(new ClientConfig(baseUrl: 'https://fixture.test', debug: true, cooldownBackend: $backend,
        retry: new RetryConfig(attempts: 3, retryExceptions: [Throwable::class])));
    if ($recording) {
        $s->transport->fake(['*' => static fn ($request) => throw new RecordingException(MockResponse::rateLimited(30)->toProviderResponse(new PreparedRequest(HttpMethod::GET, 'https://fixture.test/retry-policy')), new RuntimeException('record'))]);
    }
    $result = $s->client->send(new RetryPolicyRequest())->raw();
    expect($s->transport->getRecorded())->toHaveCount(1)->and($result->response->status)->toBe(429)
        ->and($result->debug->response->status)->toBe(429)
        ->and($result->errors->first()->context['reason'])->toBe($recording ? 'recording_failed' : 'cooldown_backend_error')
        ->and($backend->calls)->toHaveCount(3);
    if (!$recording) {
        expect($result->errors->first()->context['stage'])->toBe('cooldown_publish')
            ->and($result->exception->lastResponse)->toBe($result->response);
        $outer = (new ExecutionErrorFactory())->buildExceptionResult(new RetryPolicyRequest(), $result->exception);
        expect($outer->response)->toBe($result->response)->and($outer->errors->first()->context['reason'])->toBe('cooldown_backend_error');
    }
})->with([false, true]);

it('deadline при чтении или после HTTP сохраняет точный stage и запрещает следующий I/O', function (string $where): void {
    $clock = new VirtualClock();
    $backend = new ScriptedCooldownBackend(static function () use ($clock, $where): int {
        if ($where === 'read') { $clock->advance(1000); }
        return 0;
    }, static function (string $key, int $delay) use ($clock, $where): CooldownUpdate {
        if ($where === 'publish') { $clock->advance(1000); }
        return new CooldownUpdate(true, $delay);
    });
    $transport = new MockTransport();
    $transport->fake(['*' => static function () use ($clock, $where): MockResponse {
        if ($where === 'http') { $clock->advance(1000); }
        return MockResponse::rateLimited(30);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test', cooldownBackend: $backend,
        retry: new RetryConfig(attempts: 1, totalTimeoutMs: 1000)), $transport, $clock, $clock);
    $result = $client->send(new RetryPolicyRequest())->raw();
    expect($result->errors->first()->code)->toBe(ErrorCode::Timeout)
        ->and($result->errors->first()->context['stage'])->toBe($where === 'read' ? 'cooldown_read' : 'cooldown_publish')
        ->and($backend->calls)->toHaveCount(match ($where) { 'read' => 1, 'http' => 2, default => 3 })
        ->and($result->response?->status)->toBe($where === 'read' ? null : 429);
})->with(['read', 'http', 'publish']);

it('пересчитывает остаток retry после I/O и логирования, не начисляя I/O как sleep', function (): void {
    $clock = new VirtualClock();
    $backend = new ScriptedCooldownBackend(static function () use ($clock): int { $clock->advance(400); return 0; });
    $context = new PipelineContext(new RetryPolicyRequest(), new ClientConfig(baseUrl: 'https://fixture.test'), 'fixture');
    $context->budget = new ExecutionBudget($clock, 3000);
    $coordinator = new CooldownCoordinator(new CooldownStore($backend, new AuditLogger()), $clock, new AuditLogger());
    $spent = 0;
    $coordinator->wait(new ResolvedCooldown(new CooldownConfig(), 'key'), $context, $clock->monotonicMs() + 1000, $spent);
    expect($clock->waits)->toBe([600])->and($spent)->toBe(0)
        ->and($context->cooldownDiagnostics->reads)->toBe(2)->and($context->cooldownDiagnostics->durationMs)->toBe(800);
});

it('обычный допуск читает дважды или трижды, disabled не обращается к backend', function (bool $enabled, RateLimitBehavior $behavior, int $count): void {
    $backend = new ScriptedCooldownBackend(static fn (): int => 0);
    $s = new CooldownScenario(new ClientConfig(baseUrl: 'https://fixture.test', cooldownBackend: $backend, cooldown: new CooldownConfig(enabled: $enabled, behavior: $behavior)));
    $s->transport->fake(['*' => MockResponse::success()]);
    expect($s->client->send(new RetryPolicyRequest())->raw()->isSuccess())->toBeTrue()->and($backend->calls)->toHaveCount($count);
})->with([[true, RateLimitBehavior::Wait, 2], [true, RateLimitBehavior::Throw, 3], [false, RateLimitBehavior::Wait, 0]]);

it('общая OAuth identity переживает восстановление, новая случайная identity независима', function (): void {
    $backend = new LocalCooldownBackend();
    $tokens = new OAuth2TokenSet('access', 'refresh', 2_000_000_000, ['read']);
    $credential = new OAuth2Credential($tokens, identity: 'connection-42');
    $oauth = new OAuth2Config('https://id.test/token', 'fixture', 'secret');
    $config = new ClientConfig(baseUrl: 'https://fixture.test', cooldownBackend: $backend,
        auth: OAuth2Authenticator::authorizationCode($oauth, $credential));
    $a = new CooldownScenario($config);
    $a->client->send(new RetryPolicyRequest());
    foreach ([true, false] as $stable) {
        $restored = new OAuth2Credential(OAuth2TokenSet::restore($tokens->export()), identity: $stable ? 'connection-42' : null);
        $b = new CooldownScenario($config->with(auth: OAuth2Authenticator::authorizationCode($oauth, $restored)));
        $b->transport->fake(['*' => MockResponse::success()]);
        $result = $b->client->send(new RetryPolicyRequest())->raw();
        expect($result->isSuccess())->toBe(!$stable)->and($b->transport->getRecorded())->toHaveCount($stable ? 0 : 1);
    }
});

it('локализует backend-ошибку без изменения типа, stage и lastResponse', function (): void {
    $s = new CooldownScenario(new ClientConfig(baseUrl: 'https://fixture.test', localization: 'ru',
        cooldownBackend: new ScriptedCooldownBackend(static fn (): int => 0, static fn () => throw new RuntimeException('private'))));
    $result = $s->client->send(new RetryPolicyRequest())->raw();
    expect($result->exception)->toBeInstanceOf(CooldownBackendException::class)
        ->and($result->exception->getMessage())->toBe('Не удалось выполнить операцию хранилища cooldown.')
        ->and($result->exception->stage)->toBe('cooldown_publish')->and($result->exception->lastResponse)->toBe($result->response);
});

it('custom auth не перезапускает dependency после сбоя cooldown backend', function (bool $publish): void {
    RefreshingAuthenticator::reset();
    RefreshingAuthenticator::$shouldRefresh = true;
    $backend = new ScriptedCooldownBackend(
        static fn (): int => $publish ? 0 : throw new RuntimeException('read'),
        static fn () => throw new RuntimeException('publish'),
    );
    $s = new CooldownScenario(new ClientConfig(baseUrl: 'https://fixture.test', auth: new RefreshingAuthenticator(),
        authRetryAttempts: 3, cooldownBackend: $backend, retry: new RetryConfig(attempts: 3, retryExceptions: [Throwable::class])));
    try {
        $result = $s->client->send(new RetryPolicyRequest())->raw();
        expect($result->errors->first()->context['reason'])->toBe('cooldown_backend_error')
            ->and(RefreshingAuthenticator::$refreshCalls)->toBe(1)
            ->and($s->transport->getRecorded())->toHaveCount($publish ? 1 : 0)
            ->and($result->response?->status)->toBe($publish ? 429 : null);
    } finally { RefreshingAuthenticator::reset(); }
})->with([false, true]);
