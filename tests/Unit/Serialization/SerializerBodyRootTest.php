<?php

declare(strict_types=1);

use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Tests\Stubs\Requests\BodyRootConflictBodyRequest;
use ApiSutra\Tests\Stubs\Requests\BodyRootConflictDuplicateRequest;
use ApiSutra\Tests\Stubs\Requests\BodyRootConflictFileRequest;
use ApiSutra\Tests\Stubs\Requests\BodyRootConflictIgnoreRequest;
use ApiSutra\Tests\Stubs\Requests\BodyRootConflictUnmappedBodyRequest;
use ApiSutra\Tests\Stubs\Requests\BodyRootGetScalarRequest;
use ApiSutra\Tests\Stubs\Requests\BodyRootPatchListRequest;
use ApiSutra\Tests\Stubs\Requests\BodyRootWithQueryDefaultsRequest;
use ApiSutra\VO\Files\FileInput;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('Serializer body root', function () {
    it('сериализует root list для patch-запроса', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::None,
            environment: Environment::Testing,
        );
        $operations = [
            ['op' => 'replace', 'path' => '/params', 'value' => ['enabled' => true]],
        ];
        $request = new BodyRootPatchListRequest(
            id: '77',
            dryRun: true,
            mode: 'patch',
            operations: $operations,
        );
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($prepared->url)->toContain('/body-root/77')
            ->and($prepared->url)->toContain('dryRun=1')
            ->and($prepared->headers['X-Mode'] ?? null)->toBe('patch')
            ->and($prepared->meta['bodyIsRoot'] ?? null)->toBeTrue()
            ->and($prepared->meta['body'])->toBe($operations)
            ->and($body)->toBe($operations);
    });

    it('поддерживает root scalar на GET (advanced)', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::None,
            environment: Environment::Testing,
        );
        $request = new BodyRootGetScalarRequest('abc', 'payload');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);

        expect($prepared->url)->toContain('q=abc')
            ->and($prepared->body)->toBe('"payload"')
            ->and($prepared->meta['bodyIsRoot'] ?? null)->toBeTrue();
    });

    it('сочетается с RequestDefaults Query для не-root полей', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::None,
            environment: Environment::Testing,
        );
        $operations = [['op' => 'remove', 'path' => '/value']];
        $request = new BodyRootWithQueryDefaultsRequest(
            operations: $operations,
            plain: 'query-value',
        );
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);

        expect($prepared->url)->toContain('plain=query-value')
            ->and($prepared->meta['body'])->toBe($operations)
            ->and($prepared->meta['query']['plain']['value'] ?? null)->toBe('query-value');
    });

    it('бросает ошибку при конфликте BodyRoot и Body', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $request = new BodyRootConflictBodyRequest(
            operations: [['op' => 'replace', 'path' => '/p', 'value' => 1]],
            extra: 'x',
        );
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        expect(fn () => $serializer->serialize($request, $context))
            ->toThrow(ConfigurationException::class, 'BodyRoot cannot be combined with Body');
    });

    it('бросает ошибку при конфликте BodyRoot и body по умолчанию', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $request = new BodyRootConflictUnmappedBodyRequest(
            operations: [['op' => 'replace', 'path' => '/p', 'value' => 1]],
            plain: 'x',
        );
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        expect(fn () => $serializer->serialize($request, $context))
            ->toThrow(ConfigurationException::class, 'BodyRoot conflicts with a field included in the body by default');
    });

    it('бросает ошибку при конфликте BodyRoot и File', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $request = new BodyRootConflictFileRequest(
            operations: [['op' => 'replace', 'path' => '/p', 'value' => 1]],
            file: FileInput::fromContent('file', 'f.txt'),
        );
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        expect(fn () => $serializer->serialize($request, $context))
            ->toThrow(ConfigurationException::class, 'BodyRoot cannot be combined with File');
    });

    it('бросает ошибку при дублировании BodyRoot', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $request = new BodyRootConflictDuplicateRequest('a', 'b');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        expect(fn () => $serializer->serialize($request, $context))
            ->toThrow(ConfigurationException::class, 'More than one BodyRoot');
    });

    it('бросает ошибку при сочетании BodyRoot и Ignore на одном свойстве', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
        $request = new BodyRootConflictIgnoreRequest('payload');
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        expect(fn () => $serializer->serialize($request, $context))
            ->toThrow(ConfigurationException::class, 'BodyRoot cannot be combined with Ignore');
    });
});
