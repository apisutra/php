<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Testing\RecordingException;
use ApiSutra\Execution\BatchExecutor;
use ApiSutra\Execution\PoolExecutor;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\RetryPolicyRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\Transport\RecordingTransport;
use ApiSutra\VO\Http\PreparedRequest;

it('ошибка записи сохраняет HTTP 200 и не повторяет даже разрешённый POST', function (bool $throw, string $mode): void {
    $path = tempnam(sys_get_temp_dir(), 'apisutra-record-failure-');
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['created' => true])]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', throwOnErrors: $throw,
        retry: new RetryConfig(attempts: 3, baseDelay: 0, safeMethods: [HttpMethod::POST], retryExceptions: [Throwable::class]),
    ), new RecordingTransport($transport, $path));
    try {
        try {
            $request = new RetryPolicyRequest(HttpMethod::POST);
            $result = match ($mode) {
                'pool' => (new PoolExecutor($client, [$request]))->send()->results()->all()[0],
                'parallel' => (new BatchExecutor($client, [$request]))->parallel()->send()->results()->all()[0],
                default => $client->send($request)->raw(),
            };
            expect($throw)->toBeFalse();
            $exception = $result->exception;
            expect($result->errors->first()->code->value)->toBe('execution_error')
                ->and($result->errors->first()->context['reason'])->toBe('recording_failed')
                ->and($result->response->status)->toBe(200);
        } catch (RecordingException $exception) {
            expect($throw)->toBeTrue();
        }
        expect($exception->response->status)->toBe(200)
            ->and($exception->getMessage())->not->toContain($path)
            ->and($exception->getPrevious())->not->toBeNull()
            ->and($transport->getRecorded())->toHaveCount(1);
    } finally {
        unlink($path);
    }
})->with([false, true])->with(['sync', 'parallel', 'pool']);

it('не создаёт пустую fixture из бинарного тела с невалидным UTF-8', function (): void {
    $directory = sys_get_temp_dir() . '/apisutra-binary-' . bin2hex(random_bytes(8));
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make("\xff\xfe", 200, ['Content-Type' => 'application/octet-stream'])]);
    $recorder = new RecordingTransport($transport, $directory);
    expect(fn () => $recorder->send(new PreparedRequest(HttpMethod::GET, 'https://fixture.test')))->toThrow(RecordingException::class);
    expect(glob($directory . '/*.json'))->toBe([]);
});

it('записывает большой JSON целиком с маскированием независимо от safe limit', function (): void {
    $directory = sys_get_temp_dir() . '/apisutra-large-' . bin2hex(random_bytes(8));
    $padding = str_repeat('x', 1100000);
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['padding' => $padding, 'token' => 'fixture-secret'])]);
    try {
        (new RecordingTransport($transport, $directory))->send(new PreparedRequest(HttpMethod::GET, 'https://fixture.test'));
        $files = glob($directory . '/*.json');
        expect($files)->toHaveCount(1);
        $fixture = json_decode(file_get_contents($files[0]), true, flags: JSON_THROW_ON_ERROR);
        expect($fixture['response']['body']['padding'])->toBe($padding)
            ->and($fixture['response']['body']['token'])->toBe('***');
    } finally {
        foreach (glob($directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        if (is_dir($directory)) {
            rmdir($directory);
        }
    }
});

it('два процесса публикуют разные целые fixtures и сохраняют существующий файл', function (): void {
    $directory = sys_get_temp_dir() . '/apisutra-parallel-record-' . bin2hex(random_bytes(8));
    mkdir($directory);
    file_put_contents($directory . '/request_1.json', '{"existing":true}');
    $processes = [];
    try {
        foreach (['first', 'second'] as $name) {
            $processes[] = proc_open([PHP_BINARY, dirname(__DIR__, 2) . '/Support/recording-worker.php', $directory, $name], [], $pipes);
        }
        foreach ($processes as $process) {
            expect(proc_close($process))->toBe(0);
        }
        $files = glob($directory . '/*.json');
        expect($files)->toHaveCount(21)
            ->and(file_get_contents($directory . '/request_1.json'))->toBe('{"existing":true}')
            ->and(glob($directory . '/.recording-*'))->toBe([]);
        $workers = [];
        foreach (array_diff($files, [$directory . '/request_1.json']) as $file) {
            $fixture = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
            $workers[] = $fixture['response']['body']['worker'];
        }
        $counts = array_count_values($workers);
        ksort($counts);
        expect($counts)->toBe(['first' => 10, 'second' => 10]);
    } finally {
        foreach (glob($directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($directory);
    }
});
