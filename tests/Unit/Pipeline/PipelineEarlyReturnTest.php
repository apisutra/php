<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Tests\Stubs\HookedClient;
use ApiSutra\Tests\Stubs\Hooks\EarlyReturnHook;
use ApiSutra\Tests\Stubs\Requests\PlainRequest;
use ApiSutra\Transport\MockTransport;

describe('Pipeline early return', function () {
    it('останавливает pipeline и не вызывает транспорт', function () {
        $hooks = new ApiSutra\Hooks\HookRegistry();
        $hooks->on(Hook::BeforeSend, new EarlyReturnHook());

        $transport = new MockTransport();
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $client = new HookedClient($config, $transport, $hooks);

        $request = new PlainRequest('q');
        $request->setClient($client);

        $result = $request->send()->raw();

        expect($result->status)->toBe(ResultStatus::SUCCESS);
        expect($result->data)->toBe(['ok' => true]);
        expect($transport->getRecorded())->toHaveCount(0);
    });
});
