<?php

declare(strict_types=1);

use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Hooks\HookRecorder;
use ApiSutra\Tests\Stubs\Requests\HookedRequest;
use ApiSutra\Tests\Support\TestClientFactory;

describe('HookRunner', function () {
    it('запускает хуки из атрибутов и хук запроса по порядку', function () {
        HookRecorder::reset();

        $client = TestClientFactory::make([
            HookedRequest::class => MockResponse::success(['ok' => true]),
        ]);

        $request = new HookedRequest('payload');
        $request->setClient($client);
        $request->send();

        expect(HookRecorder::all())->toBe([
            'before-send-first',
            'before-send-normal',
            'before-send-last',
            'request-before-send',
        ]);
    });
});
