<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Tests\Stubs\Pagination\TestItemCollection;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Continuation\ContinuationState;
use ApiSutra\Exceptions\Continuation\ContinuationAwaitException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\AttributeHydration as Dto;
use ApiSutra\Tests\Stubs\HydrationRules as Wire;
use ApiSutra\Tests\Stubs\Continuation\RecordingStateResolver;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\HydrationRulesFixture as Fixture;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Files\FileInput;
use ApiSutra\VO\Pipeline\PipelineContext;

/** @return array{TestClient, MockTransport} */
function attributeEntryClient(array $payload, ?HydrationConfig $hydration = null, array $options = []): array
{
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake(['*' => MockResponse::success($payload)]);
    return [new TestClient(new ClientConfig(...array_replace(['baseUrl' => 'https://attributes.test', 'hydration' => $hydration], $options)), $transport), $transport];
}

it('включает typed items-only исключительно явным config и сохраняет Returns контейнер', function (bool $configured, bool $container, bool $invalid): void {
    $payload = ['response' => ['rows' => [['id' => $invalid ? 'bad' : 7, 'future' => false]]]];
    [$client] = attributeEntryClient($payload, $configured ? new HydrationConfig() : null);
    $result = $client->send($container ? new Dto\ContainerRequest() : new Dto\ItemsRequest())->raw();
    if ($invalid && ($configured || $container)) {
        expect($result->exception)->toBeInstanceOf(HydrationException::class)
            ->and($result->exception->sourcePath)->toBe('/response/rows/0/id');
    } else {
        $value = $result->throw()->data;
        $items = $container ? $value->items() : $value;
        if ($configured || $container) {
            expect($items[0])->toBeInstanceOf(Dto\Row::class)->and($items[0]->_extra)->toBe(['future' => false]);
        } else {
            expect($items)->toBe($payload['response']['rows']);
        }
    }
})->with([false, true])->with([false, true])->with([false, true]);

it('возвращает копию клиента к raw после with hydration null', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['response' => ['rows' => [['id' => 7]]]])]);
    $config = new ClientConfig(baseUrl: 'https://attributes.test', hydration: new HydrationConfig());
    $typed = new TestClient($config, $transport);
    $raw = new TestClient($config->with(hydration: null), $transport);
    expect($typed->send(new Dto\ItemsRequest())->dataOrFail()[0])->toBeInstanceOf(Dto\Row::class)
        ->and($raw->send(new Dto\ItemsRequest())->dataOrFail()[0])->toBe(['id' => 7]);
});

it('добавляет source к ошибке до входа в атрибутный DTO без config', function (array $payload, string $path, SourcePathKind $kind): void {
    [$client] = attributeEntryClient($payload);
    $error = $client->send(new Dto\RecordRequest())->raw()->exception;
    expect($error)->toBeInstanceOf(HydrationException::class)->and($error->sourcePath)->toBe($path)->and($error->sourcePathKind)->toBe($kind);
})->with([[[], '/data', SourcePathKind::Expected], [['data' => null], '/data', SourcePathKind::Resolved], [['data' => 7], '/data', SourcePathKind::Resolved]]);

it('сохраняет атрибуты и ошибки через promise и opaque hook', function (bool $invalid, bool $async): void {
    [$client] = attributeEntryClient(['envelope' => $invalid ? [] : ['id' => 7, 'secret-field' => 'synthetic']]);
    $result = ($async ? $client->sendAsync(new Dto\HookedReportRequest())->wait() : $client->send(new Dto\HookedReportRequest()))->raw();
    if ($invalid) {
        expect($result->exception->sourcePathKind)->toBe(SourcePathKind::Boundary);
    } else {
        expect($result->data->id)->toBe(7)->and($result->data->_extra)->toBe(['secret-field' => 'synthetic']);
    }
})->with([false, true])->with([false, true]);

it('исполняет атрибуты в CompositeFlow с границей происхождения', function (bool $invalid): void {
    [$client] = attributeEntryClient($invalid ? [] : ['id' => 7]);
    $result = $client->send((new Dto\CompositeRequest())->setClient($client))->raw();
    if ($invalid) {
        expect($result->exception)->toBeInstanceOf(HydrationException::class)
            ->and($result->exception->sourcePathKind)->toBe(SourcePathKind::Boundary);
    } else {
        expect($result->data->id)->toBe(7);
    }
})->with([false, true]);

it('доставляет ошибку Ready и повторного awaitAs без превращения в Pending', function (bool $invalid, bool $wrongShape): void {
    [$client, $transport] = attributeEntryClient(['data' => $wrongShape ? 7 : ($invalid ? [] : ['id' => 7])]);
    $handle = $client->send(new Dto\AwaitRequest());
    if ($invalid || $wrongShape) {
        try {
            $handle->await();
            throw new LogicException('Ожидалась ошибка Ready');
        } catch (ContinuationAwaitException $error) {
            expect($error->reason)->toBe('final_hydration_failed')->and($error->attempts)->toBe(1)
                ->and($error->getPrevious()->sourcePath)->toBe($wrongShape ? '/data' : '/data/id');
        }
    } else {
        expect($handle->await()->id)->toBe(7)->and($handle->awaitAs(Dto\PlainRow::class)->id)->toBe(7);
    }
    expect($transport->getRecorded())->toHaveCount(1);
})->with([false, true])->with([false, true]);

it('исключает атрибутный receiver при ручной отправке до первого hydrate', function (string $class, string $part): void {
    [$client, $transport] = attributeEntryClient([]);
    $dto = new $class(7, ['secret' => 'synthetic-secret']);
    $payload = (object) ['level' => [$dto]];
    $request = match ($part) {
        'root' => new Wire\BodyRootRequest($payload),
        'body' => new Wire\BodyRequest($payload),
        'multipart' => new Wire\MultipartRequest($payload, FileInput::fromContent('content', 'sample.txt')),
    };
    $client->send($request)->raw()->throw();
    $prepared = $transport->getRecorded()[0];
    expect($prepared->body ?? (string) $prepared->stream)->not->toContain('_extra', 'synthetic-secret')
        ->and($dto->_extra)->toBe(['secret' => 'synthetic-secret']);
})->with([Dto\Row::class, Dto\PlainRow::class])->with(['root', 'body', 'multipart']);

it('разделяет dump и wire у standalone сериализаторов в обеих формах конструктора', function (): void {
    $dto = new Dto\Row(7, ['future' => false]);
    $casts = new CastRegistry();
    expect($dto->toArray())->toBe(['id' => 7, '_extra' => ['future' => false]])
        ->and((new DtoSerializer($casts))->serialize($dto))->toBe($dto->toArray());
    foreach ([null, new HydrationConfig()] as $config) {
        foreach ([new Serializer($casts, null, $config), new Serializer($casts, config: $config)] as $serializer) {
            $request = new Wire\BodyRootRequest($dto);
            $prepared = $serializer->serialize($request, new PipelineContext($request, new ClientConfig(baseUrl: 'https://attributes.test'), 'test'));
            expect(json_decode($prepared->body, true))->toBe(['id' => 7]);
        }
    }
});

it('отказывает cast с видимым receiver до HTTP и вызова обработчика', function (string $requestClass): void {
    Wire\WireCounter::$casts = 0;
    [$client, $transport] = attributeEntryClient([]);
    $error = $client->send(new $requestClass(new Dto\PlainRow(7, ['secret' => 'synthetic'])))->raw()->exception;
    expect($error)->toBeInstanceOf(SerializationException::class)->and($transport->getRecorded())->toBe([])
        ->and(Wire\WireCounter::$casts)->toBe(0);
})->with([Wire\BodyCastRequest::class, Wire\BodyRootCastRequest::class, Wire\QueryCastRequest::class]);

it('сохраняет коллекцию factory и отсутствие itemsType на raw и typed путях', function (string $class, bool $configured): void {
    $input = ['response' => ['rows' => [['id' => 7]]]];
    [$client] = attributeEntryClient($input, $configured ? new HydrationConfig() : null);
    $result = $client->send(new $class())->dataOrFail();
    if (!$configured) {
        expect($result)->toBe($input['response']['rows']);
        return;
    }
    expect($result)->toBeInstanceOf(TestItemCollection::class);
    $first = $result->toArray()[0];
    if ($class === Dto\UntypedItemsRequest::class) {
        expect($first)->toBe(['id' => 7]);
    } else {
        expect($first)->toBeInstanceOf(Dto\Row::class);
    }
})->with([Dto\FactoryItemsRequest::class, Dto\UntypedItemsRequest::class, Dto\CollectionItemsRequest::class])->with([false, true]);

it('сохраняет opaque границу и отвергает custom representation самого receiver', function (): void {
    [$client, $transport] = attributeEntryClient([]);
    $dto = new Dto\PlainRow(7, ['visible-only-to-wrapper' => true]);
    $client->send(new Wire\BodyRootRequest(new Wire\OpaqueEnvelope($dto)))->dataOrFail();
    expect($transport->getRecorded()[0]->body)->toContain('visible-only-to-wrapper');
    foreach ([Dto\JsonReceiverDto::class, Dto\StringReceiverDto::class, Dto\ArrayReceiverDto::class] as $class) {
        $result = $client->send(new Wire\BodyRootRequest(new $class()))->raw();
        expect($result->exception)->toBeInstanceOf(ConfigurationException::class);
    }
    expect($transport->getRecorded())->toHaveCount(1);
});
