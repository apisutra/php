<?php

declare(strict_types=1);

use ApiSutra\Config\ResultExceptionConfig;
use ApiSutra\Tests\Stubs\ResultContract\RecordingFactory;
use ApiSutra\Tests\Support\ExecutionDelivery;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Core\AbstractClient;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Exceptions\Transport\ConnectionException;
use ApiSutra\Execution\BatchExecutor;
use ApiSutra\Execution\PoolExecutor;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\ProtectedRequest;
use ApiSutra\VO\Pipeline\PipelineContext;
use ApiSutra\Transport\MockTransport;

it('batch и pool сохраняют смысл ошибок независимо от throwOnErrors', function (bool $throw, string $mode, string $failure, string $code, ?int $status): void {
    $transport = new MockTransport();
    $transport->fake(['*' => static function () use ($failure): MockResponse {
        return match ($failure) {
            'http' => MockResponse::make('original', 403),
            'json' => MockResponse::make('{broken', 200, ['Content-Type' => 'application/json']),
            'hydration' => MockResponse::make('null', 200, ['Content-Type' => 'application/json']),
            'network' => throw new ConnectionException('fixture network'),
            'other' => throw new RuntimeException('fixture runtime'),
            default => MockResponse::success(['id' => 1, 'name' => 'fixture']),
        };
    }]);
    $hooks = new HookRegistry();
    if ($failure === 'hook') {
        $hooks->on(Hook::AfterResponse, new class implements HookInterface {
            public function handle(PipelineContext $context): ?array
            {
                throw new RuntimeException('fixture hook');
            }
        });
    }
    $factory = new RecordingFactory(fallback: true);
    $client = new class(new ClientConfig(baseUrl: 'https://fixture.test', throwOnErrors: $throw, resultExceptions: new ResultExceptionConfig(exceptionFactory: $factory)), $transport, $hooks) extends AbstractClient {};
    $executor = $mode === 'pool'
        ? new PoolExecutor($client, [new ProtectedRequest()])
        : (new BatchExecutor($client, [new ProtectedRequest()]))->parallel();
    $result = ExecutionDelivery::result($executor->send(...), $throw, $factory)->results()->all()[0];
    expect($result->isFailed())->toBeTrue()
        ->and($result->errors->first()->code->value)->toBe($code)
        ->and($result->response?->status)->toBe($status)
        ->and($result->errors->first()->response)->toBe($result->response);
})->with([false, true])->with(['parallel', 'pool'])->with([
    ['http', 'forbidden', 403], ['json', 'response_decoding_error', 200],
    ['hydration', 'hydration_error', 200], ['network', 'connection_failed', null],
    ['other', 'execution_error', null], ['hook', 'hook_error', 200],
]);
