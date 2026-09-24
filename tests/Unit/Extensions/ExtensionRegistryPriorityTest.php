<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeRegistry;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Extensions\ExtensionRegistry;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\Tests\Stubs\Extensions\ExactResponseExtension;
use ApiSutra\Tests\Stubs\Extensions\ExactResponseHandler;
use ApiSutra\Tests\Stubs\Extensions\WildcardResponseExtension;
use ApiSutra\Tests\Stubs\Requests\SimpleGetRequest;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('ExtensionRegistry priority', function () {
    it('выбирает обработчик с более точным mime', function () {
        $registry = new ExtensionRegistry(new CastRegistry(), new HookRegistry(), new AttributeRegistry());
        $registry->register(new WildcardResponseExtension());
        $registry->register(new ExactResponseExtension());

        $response = new ProviderResponse(
            status: 200,
            headers: ['Content-Type' => ['application/json; charset=utf-8']],
            body: '{}',
            request: new PreparedRequest(HttpMethod::GET, 'https://api.test'),
            duration: 0,
        );
        $context = new PipelineContext(
            request: new SimpleGetRequest('q'),
            config: new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            traceId: 'trace',
        );

        $handler = $registry->resolveResponseHandler($response, $context);

        expect($handler)->toBeInstanceOf(ExactResponseHandler::class);
        expect($handler?->handle($response, $context))->toBe('exact');
    });
});
