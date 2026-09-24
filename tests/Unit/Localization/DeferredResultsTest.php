<?php

declare(strict_types=1);

use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Exceptions\Continuation\ContinuationAwaitException;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\Execution\BatchExecutor;
use ApiSutra\Execution\PoolExecutor;
use ApiSutra\Localization\Message;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Continuation\InvalidFinalDto;
use ApiSutra\Tests\Stubs\Dto\ContinuationFinalDto;
use ApiSutra\Tests\Stubs\Dto\UnsupportedPrivateDto;
use ApiSutra\Tests\Stubs\Localization\ProviderException;
use ApiSutra\Tests\Stubs\Localization\Request;
use ApiSutra\Tests\Stubs\Requests\ContinuationStartRequest;
use ApiSutra\Tests\Stubs\Requests\PaginatedOverrideRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

it('локализует guard пагинации и результат итератора', function (): void {
    $transport = new MockTransport();
    $transport->fake([PaginatedOverrideRequest::class => MockResponse::success([
        'data' => [1], 'meta' => ['page' => 1, 'per_page' => 1, 'has_more' => true],
    ])]);
    $client = new TestClient(new ClientConfig(
        'https://example.test',
        environment: Environment::Testing,
        paginationConfig: new PaginationConfig(maxPages: 1),
        localization: new LocalizationConfig('ru')
    ), $transport);
    $request = new PaginatedOverrideRequest();
    $request->setClient($client);
    $result = $request->paginate()->all();
    expect($result->errors->first()->message)->toBe('Пагинация остановлена: достигнут лимит страниц')
        ->and($result->errors->first()->context['reason'])->toBe('pagination_max_pages_reached');
    $pages = iterator_to_array($request->paginate());
    expect($pages[1]->message())->toBe($result->message());
});

it('сохраняет язык ошибок batch и pool', function (string $executor): void {
    $transport = new MockTransport();
    $transport->fake([Request::class => MockResponse::success(['child' => ['count' => []]])]);
    $client = new TestClient(new ClientConfig('https://example.test', localization: new LocalizationConfig('ru')), $transport);
    $result = (new $executor(client: $client, requests: [new Request()]))->send();
    expect($result->nested[0]->message())->toStartWith('Некорректные данные')
        ->and($result->localization()->locale)->toBe('ru');
})->with([BatchExecutor::class, PoolExecutor::class]);

it('локализует cached awaitAs и сохраняет успешный кеш без нового HTTP', function (): void {
    $transport = new MockTransport();
    $transport->fake([ContinuationStartRequest::class => MockResponse::success(['data' => ['value' => 'ready']])]);
    $client = new TestClient(new ClientConfig('https://example.test', localization: new LocalizationConfig('ru')), $transport);
    $handle = $client->send(new ContinuationStartRequest('job'));
    $first = $handle->awaitAs(ContinuationFinalDto::class);
    try {
        $handle->awaitAs(InvalidFinalDto::class);
        test()->fail('Ожидалась ошибка');
    } catch (ContinuationAwaitException $exception) {
        expect($exception->reason)->toBe('final_hydration_failed')
            ->and($exception->getMessage())->toBe('Не удалось преобразовать готовый результат ожидания')
            ->and($exception->lastResult)->toBe($handle->raw())
            ->and($exception->context()['hydration']['path'])->toBe('data.count');
    }
    expect($handle->await())->toBe($first)->and($transport->getRecorded())->toHaveCount(1);
    expect(fn () => $handle->resolved()->continuationTokenOrFail())
        ->toThrow(SdkException::class, 'Continuation token отсутствует в результате');
});

it('поддерживает явный язык standalone DX-сериализатора', function (): void {
    $serializer = new DtoSerializer(new CastRegistry(), localization: new LocalizationConfig('ru'));
    expect(fn () => $serializer->serialize(new UnsupportedPrivateDto('secret')))
        ->toThrow(ConfigurationException::class, 'DTO serializer поддерживает только публичные data-свойства');
});

it('сохраняет данные пользовательского подкласса с собственной фабрикой копии', function (): void {
    $source = new ProviderException(7);
    $locale = new LocalizationConfig('ru', messages: ['en' => ['fixture.limit' => 'Limit: {limit}'], 'ru' => ['fixture.limit' => 'Лимит: {limit}']]);
    $copy = $source->localized($locale);
    expect($copy->getMessage())->toBe('Лимит: 7')->and($copy->limit)->toBe(7)
        ->and($copy->getCode())->toBe(19)->and($copy->getPrevious())->toBe($source);
});

it('отклоняет некорректное описание сообщения до форматирования', function (): void {
    expect(fn () => new Message(''))->toThrow(InvalidArgumentException::class, 'Message key must not be empty');
    expect(fn () => new Message('fixture.key', ['value' => new stdClass()]))->toThrow(InvalidArgumentException::class);
});
