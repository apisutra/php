<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\Pipeline\Hooks\HookRunner;
use ApiSutra\Tests\Stubs\Hooks\OverrideBeforeHydrateHook;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('BeforeHydrate hook', function () {
    it('использует массив, возвращаемый beforeHydrate hook', function () {
        $registry = new HookRegistry();
        $registry->on(Hook::BeforeHydrate, new OverrideBeforeHydrateHook());
        $runner = new HookRunner($registry);

        $request = new SimpleGetRequest('q');
        $context = new PipelineContext(
            request: $request,
            config: new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $data = ['id' => 0, 'name' => 'ignored'];
        $result = $runner->runBeforeHydrate($request, $context, $data);

        expect($result['id'] ?? null)->toBe(99);
        expect($result['name'] ?? null)->toBe('override');
    });

    it('переопределение beforeHydrate в request имеет приоритет', function () {
        $registry = new HookRegistry();
        $registry->on(Hook::BeforeHydrate, new OverrideBeforeHydrateHook());
        $runner = new HookRunner($registry);

        $request = new class extends AbstractRequest {
            protected function beforeHydrate(PipelineContext $context, array $data): array
            {
                return ['id' => 1, 'name' => 'request'];
            }
        };
        $context = new PipelineContext(
            request: $request,
            config: new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $data = ['id' => 0, 'name' => 'ignored'];
        $result = $runner->runBeforeHydrate($request, $context, $data);

        expect($result['id'] ?? null)->toBe(1);
        expect($result['name'] ?? null)->toBe('request');
    });
});
