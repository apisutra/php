<?php

declare(strict_types=1);

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Continuation\ContinuationAwaitOptions;
use ApiSutra\Continuation\ContinuationState;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Exceptions\Continuation\ContinuationAwaitException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Result\ContinuationTokenExtractorInterface;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Continuation\RecordingStateResolver;
use ApiSutra\Tests\Stubs\Continuation\UndeclaredRequest;
use ApiSutra\Tests\Stubs\Extensions\ExactResponseExtension;
use ApiSutra\Tests\Stubs\HydrationRules\AttributeRowsDto;
use ApiSutra\Tests\Stubs\HydrationRules\AwaitRequest;
use ApiSutra\Tests\Stubs\HydrationRules\CompositeRequest;
use ApiSutra\Tests\Stubs\HydrationRules\ContainerRequest;
use ApiSutra\Tests\Stubs\HydrationRules\ItemsRequest;
use ApiSutra\Tests\Stubs\HydrationRules\RecordDto;
use ApiSutra\Tests\Stubs\HydrationRules\RecordRequest;
use ApiSutra\Tests\Stubs\HydrationRules\ValueDto;
use ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use ApiSutra\Tests\Stubs\Requests\ContinuationPollRequest;
use ApiSutra\Tests\Stubs\Requests\DownloadCacheRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\HydrationRulesFixture as Fixture;
use ApiSutra\Tests\Support\StrictCache;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Files\FileResponse;

function entryRules(string $from = 'record_id'): HydrationRules
{
    return HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(RecordDto::class, DtoRules::create()->field('id', FieldRule::create()->from($from)));
}

/** @return array{TestClient, MockTransport} */
function entryClient(array $payload, ?HydrationRules $rules = null, array $overrides = []): array
{
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake(['*' => MockResponse::success($payload)]);
    $config = new ClientConfig(...array_replace([
        'baseUrl' => 'https://entries.test',
        'hydration' => new HydrationConfig(rules: $rules ?? entryRules()),
    ], $overrides));
    return [new TestClient($config, $transport), $transport];
}

it('сохраняет aliases each, остаток и игнорирование Cast у атрибутного Nested', function (): void {
    $rules = entryRules()->withDto(AttributeRowsDto::class, DtoRules::create()->extras('extra'));
    $source = ['rows' => [['value' => ['record_id' => 7], 'meta' => false]]];
    $dto = Hydrator::forRules($rules)->hydrate($source, AttributeRowsDto::class);
    expect($dto->items[0]->id)->toBe(7)
        ->and($dto->extra)->toBe(['rows' => [['sourceKey' => 0, 'remainder' => ['meta' => false]]]])
        ->and($source)->toBe(['rows' => [['value' => ['record_id' => 7], 'meta' => false]]]);
    $source['rows'][] = ['value' => ['record_id' => '8']];
    $error = Fixture::error(fn () => Hydrator::forRules($rules)->hydrate($source, AttributeRowsDto::class));
    expect($error->path)->toBe('items[1].id')->and($error->sourcePath)->toBe('/rows/1/value/record_id');
});

it('применяет mapping и strict через Returns sync и promise', function (bool $async): void {
    [$client, $transport] = entryClient(['record_id' => 7]);
    expect(($async ? $client->sendAsync(new RecordRequest())->wait() : $client->send(new RecordRequest()))->dataOrFail()->id)->toBe(7);
    [$client, $transport] = entryClient(['record_id' => '7']);
    $result = ($async ? $client->sendAsync(new RecordRequest())->wait() : $client->send(new RecordRequest()))->raw();
    expect($result->exception)->toBeInstanceOf(HydrationException::class)
        ->and($result->exception->sourcePath)->toBe('/record_id')->and($transport->getRecorded())->toHaveCount(1);
})->with([false, true]);

it('применяет правила к обоим входам пагинации и показывает полный путь элемента', function (string $requestClass): void {
    [$client] = entryClient(['response' => ['rows' => [['record_id' => 7]]]]);
    $value = $client->send(new $requestClass())->dataOrFail();
    $items = $requestClass === ContainerRequest::class ? $value->items() : $value;
    expect($items[0])->toBeInstanceOf(RecordDto::class)->and($items[0]->id)->toBe(7);
    [$client, $transport] = entryClient(['response' => ['rows' => [['record_id' => '7']]]]);
    $result = $client->send(new $requestClass())->raw();
    expect($result->exception)->toBeInstanceOf(HydrationException::class)
        ->and($result->exception->path)->toBe('response.rows[0].id')
        ->and($result->exception->sourcePath)->toBe('/response/rows/0/record_id')
        ->and($transport->getRecorded())->toHaveCount(1);
})->with([ItemsRequest::class, ContainerRequest::class]);

it('сохраняет прежний items-only без набора', function (): void {
    [$client] = entryClient(['response' => ['rows' => [['record_id' => '7']]]], overrides: ['hydration' => null]);
    expect($client->send(new ItemsRequest())->dataOrFail())->toBe([['record_id' => '7']]);
});

it('применяет правила к composite и отмечает произвольный aggregate границей происхождения', function (): void {
    [$client] = entryClient(['record_id' => 7]);
    expect($client->send((new CompositeRequest())->setClient($client))->dataOrFail()->id)->toBe(7);
    [$client] = entryClient(['record_id' => '7']);
    $error = $client->send((new CompositeRequest())->setClient($client))->raw()->exception;
    expect($error)->toBeInstanceOf(HydrationException::class)->and($error->path)->toBe('id')
        ->and($error->sourcePathKind)->toBe(SourcePathKind::Boundary)->and($error->sourcePath)->toBe('');
});

it('гидратирует общий HTTP cache каждым текущим набором без повторного HTTP', function (): void {
    $store = new StrictCache();
    [$client, $transport] = entryClient(['record_id' => 7, 'other_id' => 8], overrides: [
        'cacheConfig' => new CacheConfig(store: $store, prefix: 'rules-shared'),
    ]);
    $other = new TestClient($client->getConfig()->with(hydration: new HydrationConfig(rules: entryRules('other_id'))), $transport);
    expect((new RecordRequest())->setClient($client)->withCache()->dataOrFail()->id)->toBe(7)
        ->and((new RecordRequest())->setClient($other)->withCache()->dataOrFail()->id)->toBe(8)
        ->and($transport->getRecorded())->toHaveCount(1);
});

it('сохраняет отдельные ветки handler, RawResponse и Download', function (): void {
    [$client] = entryClient(['record_id' => 'invalid'], overrides: ['extensions' => [new ExactResponseExtension()]]);
    expect($client->send(new RecordRequest())->raw()->errors->first()->context)
        ->toMatchArray(['reason' => 'response_type_mismatch', 'actual' => 'string']);
    [$client] = entryClient(['record_id' => 'invalid']);
    $raw = (new CacheProbeRequest())->setClient($client)->withRawResponse()->dataOrFail();
    expect(json_decode($raw, true, flags: JSON_THROW_ON_ERROR))->toBe(['record_id' => 'invalid'])
        ->and((new DownloadCacheRequest())->setClient($client)->withoutCache()->dataOrFail())->toBeInstanceOf(FileResponse::class);
});

it('останавливает strict ошибку Ready с token и сохраняет правила для cached awaitAs', function (): void {
    $extractor = new class implements ContinuationTokenExtractorInterface {
        public function extract(ExecutionResult $result): ?string
        {
            return $result->response?->json('operationToken');
        }
    };
    [$client, $transport] = entryClient(['operationToken' => 'token', 'data' => ['record_id' => '7']], overrides: [
        'continuationTokenExtractor' => $extractor,
    ]);
    try {
        $client->send(new AwaitRequest())->await(new ContinuationAwaitOptions(3, 0));
        throw new LogicException('Ожидалась ошибка Ready');
    } catch (ContinuationAwaitException $error) {
        expect($error->reason)->toBe('final_hydration_failed')->and($error->attempts)->toBe(1)
            ->and($error->getPrevious()->path)->toBe('data.id')
            ->and($error->getPrevious()->sourcePath)->toBe('/data/record_id');
    }
    $transport->assertNotSent(ContinuationPollRequest::class);
    $rules = entryRules()->withDto(ValueDto::class, DtoRules::create()
        ->field('value', FieldRule::create()->from('record_id'))->extras('extra'));
    [$client, $transport] = entryClient(['data' => ['record_id' => 7, 'future' => false]], $rules);
    $handle = $client->send(new AwaitRequest());
    $record = $handle->await();
    $alternate = $handle->awaitAs(ValueDto::class);
    expect($record->id)->toBe(7)->and($alternate->value)->toBe(7)
        ->and($alternate->extra)->toBe(['future' => false])->and($handle->awaitAs(ValueDto::class))->toBe($alternate)
        ->and($transport->getRecorded())->toHaveCount(1);
});

it('указывает Unavailable для Ready без пути и сохраняет source ошибки формы с объявленным путём', function (): void {
    $resolver = new RecordingStateResolver();
    $resolver->state = ContinuationState::ready(['record_id' => '7']);
    [$client] = entryClient([], overrides: ['continuationStateResolver' => $resolver]);
    try {
        $client->send(new UndeclaredRequest())->awaitAs(RecordDto::class);
        throw new LogicException('Ожидалась ошибка Ready');
    } catch (ContinuationAwaitException $error) {
        expect($error->getPrevious()->sourcePathKind)->toBe(SourcePathKind::Unavailable)
            ->and($error->getPrevious()->sourcePath)->toBeNull();
    }
    [$client] = entryClient(['data' => 7]);
    try {
        $client->send(new AwaitRequest())->await();
        throw new LogicException('Ожидалась ошибка формы Ready');
    } catch (ContinuationAwaitException $error) {
        expect($error->reason)->toBe('final_hydration_failed')->and($error->getPrevious()->sourcePath)->toBe('/data')
            ->and($error->getPrevious()->sourcePathKind)->toBe(SourcePathKind::Resolved);
    }
});

it('сохраняет набор во всех пяти входах continuation', function (string $entry, bool $invalid): void {
    $extractor = new class implements ContinuationTokenExtractorInterface {
        public function extract(ExecutionResult $result): ?string
        {
            return $result->response?->json('operationToken');
        }
    };
    [$client, $transport] = entryClient([
        'phase' => 'ready', 'operationToken' => 'token', 'data' => ['record_id' => $invalid ? '7' : 7],
    ], overrides: [
        'continuationTokenExtractor' => $extractor,
        'defaultPollRequest' => ContinuationPollRequest::class,
        'continuationStateResolver' => new RecordingStateResolver(),
        'defaultContinuationMode' => match ($entry) {
            'sync' => ContinuationMode::Sync,
            'async' => ContinuationMode::Async,
            default => ContinuationMode::Auto,
        },
    ]);
    $options = new ContinuationAwaitOptions(2, 0);
    $execute = match ($entry) {
        'token' => fn () => $client->continuation()->awaitByToken('token', AwaitRequest::class, $options),
        'token-as' => fn () => $client->continuation()->awaitByTokenAs('token', RecordDto::class, $options),
        default => fn () => $client->send(new AwaitRequest())->await($options),
    };
    if ($invalid) {
        try {
            $execute();
            throw new LogicException('Ожидалась strict ошибка');
        } catch (ContinuationAwaitException $error) {
            expect($error->reason)->toBe('final_hydration_failed')->and($error->attempts)->toBe(1)
                ->and($error->getPrevious()->sourcePath)->toBe('/data/record_id');
        }
    } else {
        expect($execute()->id)->toBe(7);
    }
    expect($transport->getRecorded())->toHaveCount($entry === 'async' ? 2 : 1);
})->with(['sync', 'auto', 'async', 'token', 'token-as'])->with([false, true]);
