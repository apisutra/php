<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\HookedClient;
use ApiSutra\Tests\Stubs\Hooks\CaptureContextHook;
use ApiSutra\Tests\Stubs\Requests\PaginatedRequest;
use ApiSutra\Transport\MockTransport;

describe('PipelineContext options', function () {
    it('сохраняет RequestOptions и PaginationOptions при RequestExecution', function () {
        CaptureContextHook::reset();
        $hooks = new ApiSutra\Hooks\HookRegistry();
        $hooks->on(Hook::BeforeSend, new CaptureContextHook());

        $transport = new MockTransport();
        $transport->fake([
            PaginatedRequest::class => MockResponse::success(['data' => []]),
        ]);

        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $client = new HookedClient($config, $transport, $hooks);

        $request = new PaginatedRequest();
        $request->setClient($client);

        $request->withCache(300)->withPage(2)->withLimit(10)->send();

        $context = CaptureContextHook::$context;
        expect($context)->not->toBeNull();
        expect($context?->options)->not->toBeNull();
        expect($context?->paginationOptions)->not->toBeNull();
        expect($context?->paginationOptions?->getPage())->toBe(2);
        expect($context?->paginationOptions?->getLimit())->toBe(10);
    });
});
