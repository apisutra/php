<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\Tests\Stubs\HookedClient;
use ApiSutra\Tests\Stubs\Hooks\EarlyReturnHook;
use ApiSutra\Tests\Stubs\Requests\PlainRequest;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Transport\MockTransport;

describe('Pipeline EarlyReturn stages', function () {
    it('останавливает пайплайн на разных стадиях', function (Hook $hook, int $expectedCalls, ResultStatus $expectedStatus) {
        $hooks = new HookRegistry();
        $hooks->on($hook, new EarlyReturnHook());

        $transport = new MockTransport();
        $transport->fake([
            PlainRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $client = new HookedClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            $transport,
            $hooks,
        );

        $request = new PlainRequest('q');
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->status)->toBe($expectedStatus);
        if ($expectedStatus === ResultStatus::SUCCESS) {
            expect($result->data)->toBe(['ok' => true]);
        }
        expect($transport->getRecorded())->toHaveCount($expectedCalls);
    })->with([
        'BeforeSend' => [Hook::BeforeSend, 0, ResultStatus::SUCCESS],
        'AfterResponse' => [Hook::AfterResponse, 1, ResultStatus::SUCCESS],
        'BeforeHydrate' => [Hook::BeforeHydrate, 1, ResultStatus::SUCCESS],
        'AfterHydrate' => [Hook::AfterHydrate, 1, ResultStatus::SUCCESS],
    ]);
});
