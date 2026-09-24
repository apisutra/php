<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Tests\Stubs\MappingHttp\RoutingRequest;
use ApiSutra\Tests\Stubs\MappingHttp\RootRequest;
use ApiSutra\Tests\Stubs\MappingHttp\Trace;
use ApiSutra\Tests\Support\RequestPartsPlanCorpus;
use ApiSutra\VO\Pipeline\PipelineContext;

beforeEach(function (): void {
    Trace::$events = Trace::$arguments = [];
    Trace::$fail = null;
});

afterEach(function (): void {
    Trace::$events = Trace::$arguments = [];
    Trace::$fail = null;
});

it('сохраняет размещение, порядок эффектов и ошибки на корпусе прежнего HTTP-пути', function (): void {
    $corpus = new RequestPartsPlanCorpus();
    $corpus->run();
    foreach ($corpus->observations as $id => $observation) {
        expect($observation['actual'], $id)->toBe($observation['expected']);
    }
});

it('восстанавливается после ошибки первой компиляции и не удерживает request/context/args', function (): void {
    $serializer = new Serializer(new CastRegistry(), new AttributeMetadataCache());
    $request = new RoutingRequest();
    $context = new PipelineContext($request, new ClientConfig(baseUrl: 'https://parts.test'), 'parts');
    Trace::$fail = 'path';
    try {
        expect(fn () => $serializer->serialize($request, $context))->toThrow(LogicException::class, 'args:path');
    } finally {
        Trace::$fail = null;
    }
    $prepared = $serializer->serialize($request, $context);
    expect($prepared->meta['query']['q']['value'])->toBe(14)
        ->and($prepared->meta['body'])->toBe(['data' => ['value' => 15]]);
    $weakRequest = WeakReference::create($request);
    $weakContext = WeakReference::create($context);
    unset($prepared, $request, $context);
    gc_collect_cycles();
    expect($weakRequest->get())->toBeNull()->and($weakContext->get())->toBeNull();
    foreach (Trace::$arguments as $argument) {
        expect($argument->get())->toBeNull();
    }
});

it('разрешает URL и метод на каждом вызове общего плана и сохраняет wire-байты', function (bool $cache): void {
    $serializer = new Serializer(new CastRegistry(), new AttributeMetadataCache($cache));
    foreach ([0, 1] as $round) {
        $request = new RoutingRequest(HttpMethod::GET, '/items/{id}/{query}/{body}');
        $prepared = $serializer->serialize($request, new PipelineContext($request, new ClientConfig(baseUrl: 'https://parts.test'), 'parts'));
        expect($prepared->url)->toBe('https://parts.test/items/7/4/5?free=16&nullable=')
            ->and($prepared->body)->toBeNull();
        $request = new RoutingRequest(HttpMethod::POST);
        $prepared = $serializer->serialize($request, new PipelineContext($request, new ClientConfig(baseUrl: 'https://parts.test'), 'parts'));
        expect($prepared->url)->toBe('https://parts.test/items/7?q=14&nullable=')
            ->and($prepared->body)->toBeNull()
            ->and((string) $prepared->stream)->toContain('name="data"', '{"value":15}', 'name="free"', "\r\n16\r\n");
        $request = new RootRequest();
        $prepared = $serializer->serialize($request, new PipelineContext($request, new ClientConfig(baseUrl: 'https://parts.test'), 'parts'));
        expect($prepared->url)->toBe('https://parts.test/root?other=8')
            ->and($prepared->body)->toBe('{"id":7}');
    }
})->with([false, true]);
