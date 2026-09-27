<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Enums\DataTransfer\UnknownVariant;
use ApiSutra\Exceptions\Serialization\ResponseTypeMismatchException;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydratorInterface;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Configuration\ContinuationConfigurationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use ApiSutra\Tests\Stubs\HydrationRules\CollectionDto;
use ApiSutra\Tests\Stubs\HydrationRules\RecordDto;
use ApiSutra\Tests\Stubs\PolymorphicInput\ExternalEvent;
use ApiSutra\Tests\Stubs\PolymorphicInput\DefaultVariantCollection;
use ApiSutra\Tests\Stubs\PolymorphicInput\ExternalOne;
use ApiSutra\Tests\Stubs\PolymorphicInput\ExternalTwo;
use ApiSutra\Tests\Stubs\PolymorphicInput\TypedVariantList;
use ApiSutra\Tests\Stubs\PolymorphicInput\InvalidCollectionFallback;
use ApiSutra\Tests\Stubs\PolymorphicInput\RawDto;
use ApiSutra\Tests\Stubs\PolymorphicInput\ReadyComposite;
use ApiSutra\Tests\Stubs\PolymorphicInput\SerializableCompositeSource;
use ApiSutra\Tests\Stubs\Requests\HydrationProbeRequest;
use ApiSutra\Tests\Stubs\ResultContract\ValueExtension;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;

it('готовый abstract или interface результат не требует декларации гидратации', function (bool $composite, bool $interface): void {
    $type = $interface ? ExternalEvent::class : AbstractDto::class;
    $dto = $interface ? new ExternalOne() : new SimpleResponseDto(7, 'ready');
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 7])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://example.test', extensions: $composite ? [] : [new ValueExtension($dto)]), $transport);
    $result = $client->send($composite ? new ReadyComposite($type, $dto) : new HydrationProbeRequest($type))->raw();
    expect($result->exception)->toBeNull()->and($result->data)->toBe($dto)->and($transport->getRecorded())->toHaveCount(1);
})->with([false, true])->with([false, true]);

it('composite гидратирует массив и объекты источники в объявленный DTO', function (string $source): void {
    $data = ['id' => 7, 'name' => 'source'];
    $value = match ($source) {
        'array' => $data,
        'stdClass' => (object) $data,
        'JsonSerializable' => new SerializableCompositeSource($data),
    };
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success([])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://example.test'), $transport);
    $result = $client->send(new ReadyComposite(SimpleResponseDto::class, $value))->raw();
    expect($result->exception)->toBeNull()
        ->and($result->data)->toBeInstanceOf(SimpleResponseDto::class)
        ->and($result->data->id)->toBe(7)
        ->and($result->data->name)->toBe('source')
        ->and($transport->getRecorded())->toHaveCount(1);
})->with(['array', 'stdClass', 'JsonSerializable']);

it('Nested с default KeepRaw принимает известные варианты typed коллекции', function (bool $json, bool $configured): void {
    $hydrator = $configured ? Hydrator::forConfig(new HydrationConfig()) : Hydrator::default();
    $data = ['items' => [['type' => 'record', 'id' => 7]]];
    $dto = $json ? $hydrator->hydrateJson(json_encode($data, JSON_THROW_ON_ERROR), DefaultVariantCollection::class)
        : $hydrator->hydrate($data, DefaultVariantCollection::class);
    expect($dto->items->all())->toHaveCount(1)
        ->and($dto->items->all()[0])->toBeInstanceOf(RecordDto::class)
        ->and($dto->items->all()[0]->id)->toBe(7);
})->with([false, true])->with([false, true]);

it('Nested с default KeepRaw отклоняет неизвестный сырой элемент при сборке typed коллекции', function (bool $json, bool $configured): void {
    $hydrator = $configured ? Hydrator::forConfig(new HydrationConfig()) : Hydrator::default();
    $data = ['items' => [['type' => 'future', 'id' => 7]]];
    expect(fn () => $json ? $hydrator->hydrateJson(json_encode($data, JSON_THROW_ON_ERROR), DefaultVariantCollection::class)
        : $hydrator->hydrate($data, DefaultVariantCollection::class))
        ->toThrow(ConfigurationException::class, RecordDto::class);
})->with([false, true])->with([false, true]);

it('неверный finalType сохраняет специализированное исключение continuation', function (string $type): void {
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://example.test'), $transport);
    try {
        $client->continuation()->awaitByTokenAs('token', $type);
        test()->fail('Ожидалось исключение конфигурации');
    } catch (ContinuationConfigurationException $error) {
        expect($error->getPrevious())->toBeInstanceOf(ConfigurationException::class);
    }
    expect($transport->getRecorded())->toBe([]);
})->with(['', 'Missing\\FinalDto']);

it('проверяет fallback Nested typed collection уже на известном варианте', function (bool $json, bool $configured): void {
    $hydrator = $configured ? Hydrator::forConfig(new HydrationConfig()) : Hydrator::default();
    $data = ['items' => [['type' => 'record', 'id' => 7]]];
    try {
        $json ? $hydrator->hydrateJson(json_encode($data, JSON_THROW_ON_ERROR), InvalidCollectionFallback::class)
            : $hydrator->hydrate($data, InvalidCollectionFallback::class);
        test()->fail('Ожидалось исключение конфигурации');
    } catch (ConfigurationException $error) {
        expect($error->getMessage())->toContain(RawDto::class, InvalidCollectionFallback::class . '::$items');
    }
})->with([false, true])->with([false, true]);

it('проверяет совместимость fallback typed collection при компиляции Shape', function (): void {
    $rules = HydrationRules::create()->withDto(CollectionDto::class, DtoRules::create()->field('items', FieldRule::create()->shape(
        ValueShape::list(ValueShape::variants('type', ['record' => RecordDto::class], unknown: RawDto::class)),
    )));
    expect(fn () => Hydrator::forRules($rules))->toThrow(ConfigurationException::class, RawDto::class);
});

it('ошибка определения показывает недоступный класс map', function (string $locale): void {
    $rules = HydrationRules::create()->withVariants(ExternalEvent::class, 'type', ['x' => ExternalOne::class, 'unused' => 'Missing\\Variant']);
    $hydrator = Hydrator::forConfig(new HydrationConfig(rules: $rules), new LocalizationConfig($locale));
    expect(fn () => $hydrator->hydrateJson('{"type":"x"}', ExternalEvent::class))->toThrow(ConfigurationException::class, 'Missing\\Variant');
})->with(['en', 'ru']);


it('абстрактный контракт проверяет готовый результат но не угадывает нативную модель', function (bool $composite, bool $interface): void {
    $type = $interface ? ExternalEvent::class : AbstractDto::class;
    $foreign = new RawDto();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 7])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://example.test', extensions: $composite ? [] : [new ValueExtension($foreign)]), $transport);
    $result = $client->send($composite ? new ReadyComposite($type, $foreign) : new HydrationProbeRequest($type))->raw();
    expect($result->exception)->toBeInstanceOf($composite ? ConfigurationException::class : ResponseTypeMismatchException::class);
    $native = new TestClient(new ClientConfig(baseUrl: 'https://example.test'), $transport);
    expect($native->send(new HydrationProbeRequest($type))->raw()->exception)->toBeInstanceOf(ConfigurationException::class);
})->with([false, true])->with([false, true]);

it('Nested допускает совместимый fallback коллекции без требования карты на интерфейсе элементов', function (bool $json): void {
    $data = ['items' => [['type' => 'one'], ['type' => 'future']]];
    $hydrator = Hydrator::default();
    $dto = $json ? $hydrator->hydrateJson(json_encode($data, JSON_THROW_ON_ERROR), TypedVariantList::class) : $hydrator->hydrate($data, TypedVariantList::class);
    expect($dto->items->all()[0])->toBeInstanceOf(ExternalOne::class)
        ->and($dto->items->all()[1])->toBeInstanceOf(ExternalTwo::class);
})->with([false, true]);

it('проверяет также невыбранные map классы и KeepRaw typed коллекции', function (): void {
    foreach ([
        ValueShape::variants('type', ['record' => RecordDto::class, 'bad' => RawDto::class], unknown: UnknownVariant::Error),
        ValueShape::variants('type', ['record' => RecordDto::class]),
    ] as $shape) {
        $rules = HydrationRules::create()->withDto(CollectionDto::class, DtoRules::create()->field('items', FieldRule::create()->shape(ValueShape::list($shape))));
        expect(fn () => Hydrator::forRules($rules))->toThrow(ConfigurationException::class);
    }
    $rules = HydrationRules::create()->withDto(CollectionDto::class, DtoRules::create()->field('items', FieldRule::create()->shape(
        ValueShape::list(ValueShape::variants('type', ['record' => RecordDto::class], unknown: RecordDto::class)),
    )));
    $dto = Hydrator::forRules($rules)->hydrateJson('{"items":[{"type":"future","id":7}]}', CollectionDto::class);
    expect($dto->items->all()[0]->id)->toBe(7);
});


it('не вызывает custom гидратор для готового объекта composite', function (): void {
    $custom = new class implements DtoHydratorInterface {
        public int $calls = 0;
        public function supports(string $dtoClass): bool { $this->calls++; return true; }
        public function hydrate(array|object $data, string $dtoClass, HydrationContext $context): object
        {
            throw new LogicException('Готовый результат не должен гидратироваться повторно');
        }
    };
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success(['id' => 7])]);
    $dto = new SimpleResponseDto(7, 'ready');
    $client = new TestClient(new ClientConfig(baseUrl: 'https://example.test', hydration: new HydrationConfig(hydrator: $custom)), $transport);
    expect($client->send(new ReadyComposite(SimpleResponseDto::class, $dto))->dataOrFail())->toBe($dto)
        ->and($custom->calls)->toBe(0);
});
