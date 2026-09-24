<?php

declare(strict_types=1);

use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Enums\Hooks\HookPriority;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\Tests\Stubs\Hooks\HookRecorder;
use ApiSutra\Tests\Stubs\Hooks\NamedHook;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;

describe('HookRegistry', function () {
    it('разрешает хуки по приоритету и области', function () {
        HookRecorder::reset();
        $registry = new HookRegistry();

        $registry->on(Hook::BeforeSend, new NamedHook('global:first'), priority: HookPriority::First);
        $registry->on(Hook::BeforeSend, new NamedHook('global:normal'), priority: HookPriority::Normal);
        $registry->on(Hook::BeforeSend, new NamedHook('global:last'), priority: HookPriority::Last);

        $registry->on(
            Hook::BeforeSend,
            new NamedHook('request:first'),
            for: [SimpleGetRequest::class],
            priority: HookPriority::First,
        );
        $registry->on(
            Hook::BeforeSend,
            new NamedHook('request:normal'),
            for: [SimpleGetRequest::class],
            priority: HookPriority::Normal,
        );

        $handlers = $registry->resolve(Hook::BeforeSend, SimpleGetRequest::class, null);
        foreach ($handlers as $handler) {
            $handler->handle(new \ApiSutra\VO\Pipeline\PipelineContext(
                request: new SimpleGetRequest('q'),
                config: new ApiSutra\Config\ClientConfig(baseUrl: 'https://api.test'),
                traceId: 'trace',
            ));
        }

        expect(HookRecorder::all())->toBe([
            'global:first',
            'global:normal',
            'global:last',
            'request:first',
            'request:normal',
        ]);
    });
});
