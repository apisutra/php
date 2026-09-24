<?php

declare(strict_types=1);

use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Tests\Stubs\Requests\RequestDefaultsGetBodyRequest;
use ApiSutra\Tests\Stubs\Requests\RequestDefaultsGetNoClassAttrRequest;
use ApiSutra\Tests\Stubs\Requests\RequestDefaultsPatchQueryRequest;
use ApiSutra\Tests\Stubs\Requests\RequestDefaultsPostConventionRequest;
use ApiSutra\VO\Files\FileInput;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('Serializer request defaults', function () {
    it('сохраняет baseline behavior без class-level defaults', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::None,
            environment: Environment::Testing,
        );
        $request = new RequestDefaultsGetNoClassAttrRequest(plain: 'value');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($prepared->url)->toContain('plain=value')
            ->and($body)->toBeNull();
    });

    it('применяет class-level Body для неразмеченных свойств на GET', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::None,
            environment: Environment::Testing,
        );
        $request = new RequestDefaultsGetBodyRequest(
            id: '42',
            plain: 'plain-value',
            query: 'search',
            explicitBody: 'forced',
            mode: 'body-default',
            ignoredBody: 'ignore-me',
        );
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($prepared->url)->toContain('/defaults/42')
            ->and($prepared->url)->toContain('q=search')
            ->and($prepared->url)->not->toContain('plain=plain-value')
            ->and($prepared->headers['X-Mode'] ?? null)->toBe('body-default')
            ->and($body['plain'] ?? null)->toBe('plain-value')
            ->and($body['payload']['explicit'] ?? null)->toBe('forced')
            ->and(array_key_exists('ignoredBody', $body))->toBeFalse();
    });

    it('применяет class-level Query для неразмеченных свойств на PATCH', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::None,
            environment: Environment::Testing,
        );
        $request = new RequestDefaultsPatchQueryRequest(
            id: '43',
            plain: 'plain-value',
            forcedBody: 'forced',
            tags: ['a', 'b'],
            mode: 'query-default',
            document: FileInput::fromContent('test', 'doc.txt'),
            ignored: 'ignore-me',
        );
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $metaBody = $prepared->meta['body'] ?? [];
        $metaQuery = $prepared->meta['query'] ?? [];
        $metaFiles = $prepared->meta['files'] ?? [];

        expect($prepared->url)->toContain('/defaults/43')
            ->and($prepared->url)->toContain('plain=plain-value')
            ->and($prepared->url)->toContain('tags=a%2Cb')
            ->and($prepared->url)->not->toContain('id=43')
            ->and($prepared->headers['X-Mode'] ?? null)->toBe('query-default')
            ->and($metaBody['payload']['forced'] ?? null)->toBe('forced')
            ->and($metaQuery['plain']['value'] ?? null)->toBe('plain-value')
            ->and($metaQuery['tags']['value'] ?? null)->toBe(['a', 'b'])
            ->and($metaFiles)->toHaveCount(1)
            ->and(array_key_exists('ignored', $metaBody))->toBeFalse()
            ->and(array_key_exists('ignored', $metaQuery))->toBeFalse();
    });

    it('применяет Convention как явный режим без изменения логики', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::None,
            environment: Environment::Testing,
        );
        $request = new RequestDefaultsPostConventionRequest(plain: 'value');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($prepared->url)->not->toContain('plain=value')
            ->and($body['plain'] ?? null)->toBe('value');
    });
});
