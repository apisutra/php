<?php

declare(strict_types=1);

use ApiSutra\Auth\ApiKeyAuthenticator;
use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CredentialsEnrichmentConfig;
use ApiSutra\Diagnostics\RedactionPolicy;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Transport\RecordingTransport;
use ApiSutra\VO\Audit\DebugInfo;
use ApiSutra\VO\Http\PreparedRequest;
use GuzzleHttp\Psr7\Utils;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

it('использует политику клиента в logger и requestDebug, сохраняя отправленные credentials', function (): void {
    $logger = new class extends AbstractLogger {
        /** @var list<array<string, mixed>> */
        public array $records = [];

        public function log(mixed $level, Stringable|string $message, array $context = []): void
        {
            $this->records[] = ['message' => (string) $message, 'context' => $context];
        }
    };
    $transport = new MockTransport();
    $transport->fake([SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'Example'])]);
    $policy = new RedactionPolicy(headers: ['X-Provider']);
    $config = new ClientConfig(
        baseUrl: 'https://api.test',
        credentialsConfig: new CredentialsEnrichmentConfig(
            query: ['provider_credential' => 'fixture-secret'],
            secretKeys: ['provider_credential'],
        ),
        auth: new ApiKeyAuthenticator('fixture-secret', header: 'Authorization'),
        logger: $logger,
        logLevel: LogLevel::DEBUG,
        debug: true,
        environment: Environment::Testing,
        redaction: $policy,
    );
    $client = new TestClient($config->with(timeout: 5), $transport);
    $result = (new SimpleGetRequest('q'))->setClient($client)->withHeader('X-Provider', 'fixture-secret')->send()->raw();

    expect($result->requestDebugJson())->not->toContain('fixture-secret')
        ->and(json_encode($logger->records, JSON_THROW_ON_ERROR))->not->toContain('fixture-secret')
        ->and($result->requestDebugJson(false))->toContain('fixture-secret')
        ->and($transport->getRecorded()[0]->headers['Authorization'])->toBe('fixture-secret')
        ->and($transport->getRecorded()[0]->headers['X-Provider'])->toBe('fixture-secret');
});

it('recorder маскирует credentials без Fixture и не потребляет stream', function (bool $useStream): void {
    $directory = sys_get_temp_dir() . '/apisutra-redaction-' . bin2hex(random_bytes(8));
    $stream = Utils::streamFor('prefix-file-content');
    $stream->seek(7);
    $prepared = new PreparedRequest(
        method: HttpMethod::POST,
        url: 'https://api.test/upload?access_token=fixture-secret',
        headers: ['aUtHoRiZaTiOn' => 'fixture-secret', 'Content-Type' => 'application/json'],
        body: $useStream ? null : '{"token":"fixture-secret","nested":{"custom":"fixture-secret"}}',
        stream: $useStream ? $stream : null,
        meta: ['credentialsEnrichment' => ['secretKeys' => ['custom']]],
    );
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make(
        ['access_token' => 'fixture-secret', 'ok' => true],
        headers: ['Set-Cookie' => 'session=fixture-secret'],
    )]);
    $recorder = new RecordingTransport($transport, $directory);
    try {
        $response = $recorder->send($prepared);
        $files = glob($directory . '/*.json');
        expect($files)->toHaveCount(1);
        $json = file_get_contents($files[0]);
        expect($json)->not->toContain('fixture-secret')
            ->and($response->body)->toContain('fixture-secret')
            ->and($stream->tell())->toBe(7)
            ->and($prepared->headers['aUtHoRiZaTiOn'])->toBe('fixture-secret');
        $debug = new ExecutionResult(null, ResultStatus::SUCCESS, new ErrorCollection([]), debug: new DebugInfo($prepared));
        expect($debug->requestDebugJson())->not->toContain('fixture-secret')
            ->and($stream->tell())->toBe(7);
    } finally {
        foreach (glob($directory . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
        $stream->close();
    }
})->with([false, true]);

it('client record сохраняет дополнительные правила конфигурации без Fixture', function (): void {
    $directory = sys_get_temp_dir() . '/apisutra-client-record-' . bin2hex(random_bytes(8));
    $transport = new MockTransport();
    $transport->fake([SimpleGetRequest::class => MockResponse::success(['id' => 1, 'name' => 'Example'])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://api.test',
        redaction: new RedactionPolicy(headers: ['X-Provider-Credential']),
        environment: Environment::Testing,
    ), $transport);
    try {
        $client->record($directory);
        $result = (new SimpleGetRequest('q'))->setClient($client)
            ->withHeader('X-Provider-Credential', 'fixture-secret')->send()->raw();
        expect($result->isSuccess())->toBeTrue();
        $files = glob($directory . '/*.json');
        expect($files)->toHaveCount(1)
            ->and(file_get_contents($files[0]))->not->toContain('fixture-secret')
            ->and($transport->getRecorded()[0]->headers['X-Provider-Credential'])->toBe('fixture-secret');
    } finally {
        foreach (glob($directory . '/*.json') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

it('ограничивает safe тело до декодирования и позволяет увеличить лимит сохраняя маскирование', function (): void {
    $body = json_encode(['padding' => str_repeat('x', 70000), 'token' => 'fixture-secret'], JSON_THROW_ON_ERROR);
    $prepared = new PreparedRequest(HttpMethod::POST, 'https://fixture.test', body: $body, meta: ['body' => ['padding' => str_repeat('x', 70000)]]);
    $result = new ExecutionResult(null, ResultStatus::SUCCESS, new ErrorCollection([]), debug: new DebugInfo($prepared));
    $safe = $result->requestDebug();
    expect($safe['bodyRaw'])->toBeNull()->and($safe['body'])->toBeNull()
        ->and($safe['bodyOmissionReason'])->toBe('body_size_limit')->and($safe['bodySize'])->toBe(strlen($body))
        ->and($result->requestDebug(false)['bodyRaw'])->toBe($body);
    $policy = new RedactionPolicy(maxBodyBytes: 100000);
    $larger = new ExecutionResult(null, ResultStatus::SUCCESS, new ErrorCollection([]), debug: new DebugInfo($prepared), redaction: $policy);
    expect($larger->requestDebug()['bodyRaw'])->toContain('padding')->not->toContain('fixture-secret')
        ->and($larger->requestDebug()['bodyOmitted'])->toBeFalse();
    $context = (new RedactionPolicy())->context(['response' => ['body' => $body, 'headers' => ['Authorization' => 'fixture-secret']]]);
    expect($context['response']['body'])->toBeNull()->and($context['response']['bodySize'])->toBe(strlen($body))
        ->and(json_encode($context))->not->toContain('fixture-secret');
});
