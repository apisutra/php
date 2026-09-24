<?php

declare(strict_types=1);

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Tests\Support\StrictCache;
use ApiSutra\Tests\Stubs\HydrationRules\OpaqueEnvelope;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\RequestPartsCollector;
use ApiSutra\Serialization\Rules\ReceiverOutput;
use ApiSutra\Serialization\Rules\RuleSetCompiler;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\HydrationRules\BodyCastRequest;
use ApiSutra\Tests\Stubs\HydrationRules\BodyRequest;
use ApiSutra\Tests\Stubs\HydrationRules\BodyRootCastRequest;
use ApiSutra\Tests\Stubs\HydrationRules\BodyRootRequest;
use ApiSutra\Tests\Stubs\HydrationRules\MultipartRequest;
use ApiSutra\Tests\Stubs\HydrationRules\OpaqueCast;
use ApiSutra\Tests\Stubs\HydrationRules\OwnerDto;
use ApiSutra\Tests\Stubs\HydrationRules\QueryCastRequest;
use ApiSutra\Tests\Stubs\HydrationRules\QueryRequest;
use ApiSutra\Tests\Stubs\HydrationRules\ReceiverOnlyDto;
use ApiSutra\Tests\Stubs\HydrationRules\ReceiverOutputAttributesDto;
use ApiSutra\Tests\Stubs\HydrationRules\TypedWireRequest;
use ApiSutra\Tests\Stubs\HydrationRules\WireCounter;
use ApiSutra\Tests\Stubs\HydrationRules\WireDto;
use ApiSutra\Tests\Stubs\HydrationRules\WirePlainDto;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Files\FileInput;

function receiverWireRules(): HydrationRules
{
    $rules = HydrationRules::create();
    foreach ([OwnerDto::class, WireDto::class, WirePlainDto::class, ReceiverOnlyDto::class] as $class) {
        $rules = $rules->withDto($class, DtoRules::create()->extras('extra'));
    }
    return $rules;
}

/**
     * @param array<string, mixed> $overrides
     * @return array{TestClient, MockTransport}
     */
function receiverWireClient(?HydrationRules $rules, array $overrides = []): array
{
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake(['*' => MockResponse::success([])]);
    $config = new ClientConfig(...array_replace(['baseUrl' => 'https://rules.test', 'hydration' => new HydrationConfig(rules: $rules)], $overrides));
    return [new TestClient($config, $transport), $transport];
}

beforeEach(function (): void {
    WireCounter::$constructed = 0;
    WireCounter::$casts = 0;
});

it('исключает receiver у ручных plain и DtoInterface моделей во всех сериализуемых частях', function (string $class, string $part, string $container): void {
    [$client, $transport] = receiverWireClient(receiverWireRules());
    $dto = new $class(id: 7, extra: ['secret' => 'synthetic-secret']);
    $payload = match ($container) {
        'root' => $dto,
        'list' => [$dto],
        'nested' => (object) ['level1' => ['level2' => (object) ['dto' => $dto]]],
    };
    $request = match ($part) {
        'root' => new BodyRootRequest($payload),
        'body' => new BodyRequest($payload),
        'query' => new QueryRequest($payload),
        'multipart' => new MultipartRequest($payload, FileInput::fromContent('file-content', 'fixture.txt')),
    };
    $constructed = WireCounter::$constructed;
    if ($part === 'query') {
        $rules = receiverWireRules();
        $serializer = new DtoSerializer(new CastRegistry(), config: new HydrationConfig(rules: $rules));
        $collector = new RequestPartsCollector(
            new CastRegistry(),
            null,
            $serializer->serialize(...),
            new ReceiverOutput(new RuleSetCompiler(new HydrationConfig(rules: $rules))),
        );
        $parts = $collector->collect($request, null, [], [], HttpMethod::GET);
        expect(json_encode($parts->query, JSON_THROW_ON_ERROR))->not->toContain('extra', 'synthetic-secret');
        $result = $client->send($request)->raw();
        expect($result->exception)->toBeInstanceOf(SerializationException::class)
            ->and($transport->getRecorded())->toBe([])
            ->and(WireCounter::$constructed)->toBe($constructed)
            ->and($dto->extra)->toBe(['secret' => 'synthetic-secret']);
        return;
    }
    $result = $client->send($request)->raw()->throw();
    expect($result->isFailed())->toBeFalse()->and(WireCounter::$constructed)->toBe($constructed)
        ->and($dto->extra)->toBe(['secret' => 'synthetic-secret']);
    $prepared = $transport->getRecorded()[0];
    $wire = $prepared->body ?? (string) $prepared->stream;
    expect($wire)->not->toContain('extra', 'synthetic-secret');
    if ($part === 'multipart') {
        expect($wire)->toContain('payload', 'file-content');
    } else {
        expect($wire)->toContain('"id":7');
    }
})->with([OwnerDto::class, WireDto::class])->with(['root', 'body', 'query', 'multipart'])->with(['root', 'list', 'nested']);

it('сохраняет JSON остальных свойств plain модели и пустой JSON объект', function (): void {
    $dto = new WirePlainDto(extra: ['future' => false]);
    [$without, $oldTransport] = receiverWireClient(null);
    $without->send(new BodyRootRequest($dto))->dataOrFail();
    $expected = json_decode($oldTransport->getRecorded()[0]->body, true, flags: JSON_THROW_ON_ERROR);
    unset($expected['extra']);
    [$client, $transport] = receiverWireClient(receiverWireRules());
    $client->send(new BodyRootRequest($dto))->dataOrFail();
    expect(json_decode($transport->getRecorded()[0]->body, true, flags: JSON_THROW_ON_ERROR))->toBe($expected)
        ->and($transport->getRecorded()[0]->body)->toContain('"empty":{}')->and(WireCounter::$constructed)->toBe(1);
    $client->send(new BodyRootRequest(new ReceiverOnlyDto(['future' => 1])))->dataOrFail();
    expect($transport->getRecorded()[1]->body)->toBe('{}');
});

it('сохраняет extra у DX сериализатора, клиента без набора и набора без receiver', function (): void {
    $dto = new WireDto(extra: ['future' => false]);
    expect($dto->toArray())->toBe(['id' => 7, 'extra' => ['future' => false]])
        ->and((new DtoSerializer(new CastRegistry()))->serialize($dto))->toBe($dto->toArray());
    foreach ([null, HydrationRules::create()->withDto(WireDto::class, DtoRules::create())] as $rules) {
        [$client, $transport] = receiverWireClient($rules);
        $client->send(new BodyRootRequest($dto))->dataOrFail();
        expect(json_decode($transport->getRecorded()[0]->body, true))->toBe($dto->toArray());
    }
});

it('отклоняет непрозрачный cast с receiver до его вызова и до HTTP', function (string $requestClass): void {
    [$client, $transport] = receiverWireClient(receiverWireRules(), ['casts' => [WirePlainDto::class => new OpaqueCast()]]);
    $request = new $requestClass(new WirePlainDto(extra: ['secret' => 'synthetic-secret']));
    $result = $client->send($request)->raw();
    expect($result->exception)->toBeInstanceOf(SerializationException::class)
        ->and($result->errors->first()->code->value)->toBe('serialization_error')
        ->and($transport->getRecorded())->toBe([])->and(WireCounter::$casts)->toBe(0)
        ->and($result->exception->getMessage())->not->toContain('synthetic-secret');
})->with([BodyRootCastRequest::class, BodyCastRequest::class, QueryCastRequest::class, TypedWireRequest::class]);

it('отклоняет каждый исходящий атрибут receiver при компиляции', function (string $property): void {
    $rules = HydrationRules::create()->withDto(ReceiverOutputAttributesDto::class, DtoRules::create()->extras($property));
    expect(fn () => Hydrator::forRules($rules))->toThrow(ConfigurationException::class);
})->with(['to', 'date', 'query', 'body', 'root', 'header', 'path', 'file']);

it('строит requestDebug, HTTP cache и лог без receiver', function (): void {
    $logger = new class extends AbstractLogger {
        public array $entries = [];
        public function log(mixed $level, Stringable|string $message, array $context = []): void
        {
            $this->entries[] = ['message' => (string) $message, 'context' => $context];
        }
    };
    [$client, $transport] = receiverWireClient(receiverWireRules(), [
        'cacheConfig' => new CacheConfig(store: new StrictCache(), prefix: 'wire'),
        'logger' => $logger, 'logLevel' => LogLevel::DEBUG, 'debug' => true,
    ]);
    foreach (['synthetic-secret-a', 'synthetic-secret-b'] as $secret) {
        $request = (new BodyRootRequest(new WireDto(extra: ['secret' => $secret])))->setClient($client);
        $handle = $request->withCache()->send();
        $handle->dataOrFail();
        expect(json_encode($handle->requestDebug(false)))->not->toContain($secret, 'extra');
    }
    expect($transport->getRecorded())->toHaveCount(1)
        ->and(json_encode($logger->entries))->not->toContain('synthetic-secret-a', 'synthetic-secret-b');
});

it('сохраняет документированную границу непрозрачной внешней обёртки', function (): void {
    [$client, $transport] = receiverWireClient(receiverWireRules());
    $client->send(new BodyRootRequest(new OpaqueEnvelope(new WireDto(extra: ['future' => 1]))))->dataOrFail();
    expect(json_decode($transport->getRecorded()[0]->body, true))->toBe(['opaque' => ['id' => 7, 'extra' => ['future' => 1]]]);
});
