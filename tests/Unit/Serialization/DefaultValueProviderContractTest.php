<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Hydration\AddressDto;
use ApiSutra\Tests\Stubs\Hydration\CheckedListDto;
use ApiSutra\Tests\Stubs\Hydration\ContractRequest;
use ApiSutra\Tests\Stubs\Hydration\InvalidDefaultDto;
use ApiSutra\Tests\Stubs\Hydration\ProviderEnvelopeDto;
use ApiSutra\Tests\Stubs\Hydration\ProviderStatesDto;
use ApiSutra\Tests\Stubs\Hydration\ProviderPolicyDto;
use ApiSutra\Tests\Stubs\Hydration\RejectedValueDto;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

it('дополняет ошибку provider полем на каждом уровне и сохраняет причину', function (
    string $type,
    array $payload,
    string $state,
    string $path,
): void {
    try {
        Hydrator::default()->hydrate($payload, $type);
        test()->fail('Provider должен был отказать');
    } catch (HydrationException $error) {
        expect($error->reason)->toBe('provider_rejected_value')->and($error->path)->toBe($path)
            ->and($error->expected)->toBe('accepted value')->and($error->actual)->toBe($state);
        $cause = $error;
        while ($cause->getPrevious() !== null) {
            $cause = $cause->getPrevious();
        }
        expect($cause)->toBeInstanceOf(DomainException::class);
    }
})->with([
    [RejectedValueDto::class, [], 'missing', 'count'],
    [RejectedValueDto::class, ['count' => null], 'null', 'count'],
    [RejectedValueDto::class, ['count' => 7], 'present', 'count'],
    [RejectedValueDto::class, ['count' => 7, 'detail' => true], 'present', 'count.detail'],
    [ProviderEnvelopeDto::class, ['child' => ['count' => null]], 'null', 'child.count'],
    [ProviderEnvelopeDto::class, ['child' => []], 'missing', 'child.count'],
    [ProviderEnvelopeDto::class, ['child' => ['count' => 7, 'detail' => true]], 'present', 'child.count.detail'],
    [ProviderEnvelopeDto::class, ['items' => [[]]], 'missing', 'items[0].count'],
    [ProviderEnvelopeDto::class, ['items' => [['count' => null]]], 'null', 'items[0].count'],
    [ProviderEnvelopeDto::class, ['items' => [['count' => 7]]], 'present', 'items[0].count'],
]);

it('доставляет полный путь provider через unwrap и result-first', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['data' => ['items' => [['count' => null]]]])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), $transport);
    $result = $client->send(new ContractRequest(ProviderEnvelopeDto::class, 'data'))->raw();

    expect($result->isFailed())->toBeTrue()->and($result->response->status)->toBe(200)
        ->and($result->exception->path)->toBe('data.items[0].count')
        ->and($result->errors->first()->context)->toMatchArray([
            'path' => 'data.items[0].count', 'reason' => 'provider_rejected_value',
        ])
        ->and($transport->getRecorded())->toHaveCount(1);
});

it('проверяет Present перед Nested и оставляет известные и неизвестные варианты его обработчику', function (): void {
    $unknown = ['kind' => 'future', 'enabled' => false];
    $dto = Hydrator::default()->hydrate(
        ['items' => [['kind' => 'known', 'city' => 'Sample'], $unknown]],
        CheckedListDto::class,
    );
    expect($dto->items[0])->toBeInstanceOf(AddressDto::class)->and($dto->items[0]->city)->toBe('Sample')
        ->and($dto->items[1])->toBe($unknown);
});

it('применяет один Null/Present provider к форме списка, сохраняя точный путь', function (
    array $payload,
    string $reason,
    string $path,
): void {
    try {
        Hydrator::default()->hydrate($payload, CheckedListDto::class);
        test()->fail('Некорректный список принят');
    } catch (HydrationException $error) {
        expect($error->reason)->toBe($reason)->and($error->path)->toBe($path);
    }
})->with([
    [['items' => null], 'invalid_list_shape', 'items'],
    [['items' => ['key' => ['kind' => 'known', 'city' => 'Sample']]], 'invalid_list_shape', 'items'],
    [['items' => [1 => ['kind' => 'known', 'city' => 'Sample']]], 'invalid_list_shape', 'items'],
    [['items' => [['kind' => 'known', 'city' => []]]], 'invalid_field_type', 'items[0].city'],
]);

it('сохраняет constructor default при Missing и принимает пустой список', function (): void {
    expect(Hydrator::default()->hydrate([], CheckedListDto::class)->items)->toBeNull()
        ->and(Hydrator::default()->hydrate(['items' => []], CheckedListDto::class)->items)->toBe([]);
});

it('сохраняет порядок Keep, нормализации, provider и Cast', function (): void {
    $dto = Hydrator::default()->hydrate(['keep' => '', 'normalized' => '', 'cast' => ''], ProviderStatesDto::class);
    expect($dto->keep)->toBe('present')->and($dto->normalized)->toBe('null')->and($dto->cast)->toBe('PRESENT');
});

it('передаёт provider состояние после нормализации политикой DTO', function (): void {
    expect(Hydrator::default()->hydrate(['value' => '  '], ProviderPolicyDto::class)->value)->toBe('null');
});

it('не превращает неверную конфигурацию DefaultValue в ошибку данных', function (): void {
    expect(fn (): object => Hydrator::default()->hydrate(['value' => null], InvalidDefaultDto::class))
        ->toThrow(ConfigurationException::class);
});
