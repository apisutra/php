<?php

declare(strict_types=1);

use ApiSutra\Casts\CastRegistry;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Tests\Stubs\Collections\OutputItemCollection;
use ApiSutra\Tests\Stubs\Dto\CastAttributeDto;
use ApiSutra\Tests\Stubs\Dto\CastStringDto;
use ApiSutra\Tests\Stubs\Dto\CollectionAutoDefaultDto;
use ApiSutra\Tests\Stubs\Dto\BlankStringNullableDto;
use ApiSutra\Tests\Stubs\Dto\CollectionExplicitDefaultDto;
use ApiSutra\Tests\Stubs\Dto\CollectionNullDefaultDto;
use ApiSutra\Tests\Stubs\Dto\ComplexDto;
use ApiSutra\Tests\Stubs\Dto\ConstructorDefaultDto;
use ApiSutra\Tests\Stubs\Dto\EmptyStringCastPriorityDto;
use ApiSutra\Tests\Stubs\Dto\EmptyStringDefaultValueDto;
use ApiSutra\Tests\Stubs\Dto\EmptyStringNonNullableDto;
use ApiSutra\Tests\Stubs\Dto\EmptyStringNullableDto;
use ApiSutra\Tests\Stubs\Dto\EmptyStringProfileNullableDto;
use ApiSutra\Tests\Stubs\Dto\FallbackDefaultDto;
use ApiSutra\Tests\Stubs\Dto\InheritedEmptyStringNullableDto;
use ApiSutra\Tests\Stubs\Dto\InheritedNullablePayloadDto;
use ApiSutra\Tests\Stubs\Dto\InheritedRequiredPayloadDto;
use ApiSutra\Tests\Stubs\Dto\MapBidirectionalDto;
use ApiSutra\Tests\Stubs\Dto\MapPriorityDto;
use ApiSutra\Tests\Stubs\Dto\NestedItemDto;
use ApiSutra\Tests\Stubs\Dto\NullableCollectionAutoDefaultDto;
use ApiSutra\Tests\Stubs\Dto\ScalarAutoCastDto;
use ApiSutra\Tests\Stubs\Dto\ScalarInvalidIntDto;
use ApiSutra\Tests\Stubs\Enums\TestStatus;
use ApiSutra\Tests\Stubs\Requests\SerializationRequest;
use ApiSutra\Tests\Stubs\Support\StringValue;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('Hydrator', function () {
    it('гидрирует вложенные структуры и enum', function () {
        $data = [
            'status' => 'active',
            'items' => [
                ['id' => 1, 'name' => 'First'],
                ['id' => 2, 'name' => 'Second'],
            ],
            'meta' => [
                'inner' => [
                    'value' => 'inside',
                ],
            ],
            'tags' => [
                ['value' => 'one'],
                ['value' => 'two'],
            ],
        ];

        $dto = ComplexDto::from($data);

        expect($dto->status)->toBe(TestStatus::Active);
        expect($dto->items)->toHaveCount(2);
        expect($dto->items[0])->toBeInstanceOf(NestedItemDto::class);
        expect($dto->items[0]->id)->toBe(1);
        expect($dto->innerValue)->toBe('inside');
        expect($dto->tags)->toBe(['one', 'two']);
    });

    it('гидрирует Map двусторонне и использует NamingStrategy как fallback для остальных полей', function () {
        $hydrator = new Hydrator(new CastRegistry());
        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: new ClientConfig(
                baseUrl: 'https://api.test',
                namingStrategy: NamingStrategy::SnakeCase,
                environment: Environment::Testing,
            ),
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = $hydrator->hydrate([
            'query_num' => '42',
            'plain_value' => 'ok',
        ], MapBidirectionalDto::class, $context);

        expect($dto->queryNumber)->toBe('42')
            ->and($dto->plainValue)->toBe('ok');
    });

    it('автоматически подставляет пустую typed collection при missing для non-nullable свойства', function () {
        $dto = CollectionAutoDefaultDto::from([]);

        expect($dto->items)->toBeInstanceOf(OutputItemCollection::class)
            ->and($dto->items->isEmpty())->toBeTrue();
    });

    it('не подставляет typed collection автоматически для nullable свойства при missing', function () {
        $dto = NullableCollectionAutoDefaultDto::from([]);

        expect($dto->items)->toBeNull();
    });

    it('не подставляет typed collection автоматически для nullable свойства при null', function () {
        $dto = NullableCollectionAutoDefaultDto::from([
            'items' => null,
        ]);

        expect($dto->items)->toBeNull();
    });

    it('сохраняет приоритет DefaultValue над автодефолтом typed collection', function () {
        $dto = CollectionExplicitDefaultDto::from([]);

        expect($dto->items->count())->toBe(1)
            ->and($dto->items->first()?->id)->toBe(99)
            ->and($dto->items->first()?->label)->toBe('Default');
    });

    it('не ломает built-in missing fallback, если DefaultValue покрывает только null', function () {
        $dto = CollectionNullDefaultDto::from([]);

        expect($dto->items)->toBeInstanceOf(OutputItemCollection::class)
            ->and($dto->items->isEmpty())->toBeTrue();
    });

    it('применяет DefaultValue для null отдельно от built-in missing fallback', function () {
        $dto = CollectionNullDefaultDto::from([
            'items' => null,
        ]);

        expect($dto->items)->toBeInstanceOf(OutputItemCollection::class)
            ->and($dto->items->isEmpty())->toBeTrue();
    });

    it('безопасно автокастует scalar значения по declared type', function () {
        $dto = ScalarAutoCastDto::from([
            'intValue' => '12',
            'floatValue' => '12.5',
            'boolValue' => 'true',
            'stringValue' => 42,
        ]);

        expect($dto->intValue)->toBe(12)
            ->and($dto->floatValue)->toBe(12.5)
            ->and($dto->boolValue)->toBeTrue()
            ->and($dto->stringValue)->toBe('42');
    });

    it('автокастует bool только из безопасных представлений', function () {
        $dtoTrue = ScalarAutoCastDto::from(['boolValue' => '1']);
        $dtoFalse = ScalarAutoCastDto::from(['boolValue' => 'false']);

        expect($dtoTrue->boolValue)->toBeTrue()
            ->and($dtoFalse->boolValue)->toBeFalse();
    });

    it('автокастует string из Stringable', function () {
        $dto = ScalarAutoCastDto::from([
            'stringValue' => new StringValue('wrapped'),
        ]);

        expect($dto->stringValue)->toBe('wrapped');
    });

    it('не делает небезопасный int cast для нечисловой строки', function () {
        expect(fn () => ScalarInvalidIntDto::from([
            'intValue' => 'abc',
        ]))->toThrow(HydrationException::class);
    });

    it('дает From приоритет над Map при гидрации', function () {
        $hydrator = new Hydrator(new CastRegistry());

        $dto = $hydrator->hydrate([
            'from_key' => 'from',
            'shared_key' => 'shared',
            'to_key' => 'to',
            'ownersheep_type' => 'lease',
        ], MapPriorityDto::class);

        expect($dto->fromPreferred)->toBe('from')
            ->and($dto->toPreferred)->toBe('shared')
            ->and($dto->ownershipType)->toBe('lease');
    });

    it('применяет каст по типу из hydration profile DTO', function () {
        $hydrator = new Hydrator(new CastRegistry());

        $dto = $hydrator->hydrate(['value' => 'hello'], CastStringDto::class);

        expect($dto->value)->toBe('HELLO');
    });

    it('применяет каст из атрибута', function () {
        $dto = CastAttributeDto::from(['value' => 'hello']);

        expect($dto->value)->toBe('HELLO');
    });

    it('сохраняет дефолт свойства при отсутствии ключа', function () {
        $dto = ConstructorDefaultDto::from([]);

        expect($dto->middleName)->toBe('somename');
    });

    it('перезаписывает дефолт при явном null', function () {
        $dto = ConstructorDefaultDto::from(['middle_name' => null]);

        expect($dto->middleName)->toBeNull();
    });

    it('использует fallback для From при отсутствии primary', function () {
        $dto = FallbackDefaultDto::from(['secondary' => 'alt']);

        expect($dto->name)->toBe('alt');
    });

    it('не использует fallback если primary найден как null', function () {
        $dto = FallbackDefaultDto::from(['primary' => null, 'secondary' => 'alt']);

        expect($dto->name)->toBeNull();
    });

    it('применяет DefaultValue при missing и null', function () {
        $dtoMissing = FallbackDefaultDto::from([]);
        $dtoNull = FallbackDefaultDto::from(['status' => null]);

        expect($dtoMissing->status)->toBe('unknown');
        expect($dtoNull->status)->toBe('unknown');
    });

    it('не применяет DefaultValue если значение присутствует', function () {
        $dto = FallbackDefaultDto::from(['status' => 'ready']);

        expect($dto->status)->toBe('ready');
    });

    it('применяет DefaultValue только при Null для заданного when', function () {
        $dtoMissing = FallbackDefaultDto::from([]);
        $dtoNull = FallbackDefaultDto::from(['code' => null]);
        $dtoPresent = FallbackDefaultDto::from(['code' => 'value']);

        expect($dtoMissing->code)->toBe('code-default');
        expect($dtoNull->code)->toBe('fallback');
        expect($dtoPresent->code)->toBe('value');
    });

    it('использует provider для DefaultValue', function () {
        $dto = FallbackDefaultDto::from(['marker' => 'x']);

        expect($dto->provided)->toBe('provider:missing:x');
    });

    it('передает состояние null в provider', function () {
        $dto = FallbackDefaultDto::from([
            'provided' => null,
            'marker' => 'z',
        ]);

        expect($dto->provided)->toBe('provider:null:z');
    });

    it('использует fallback для Nested', function () {
        $dto = FallbackDefaultDto::from([
            'alt_items' => [
                ['id' => 1, 'name' => 'First'],
                ['id' => 2, 'name' => 'Second'],
            ],
        ]);

        expect($dto->items)->toHaveCount(2);
        expect($dto->items[0])->toBeInstanceOf(NestedItemDto::class);
    });

    it('EmptyStringAsNull преобразует пустую строку в null', function () {
        $dto = EmptyStringNullableDto::from([
            'name' => '',
        ]);

        expect($dto->name)->toBeNull();
    });

    it('EmptyStringAsNull(blank: true) преобразует blank string в null', function () {
        $dto = BlankStringNullableDto::from([
            'name' => '   ',
        ]);

        expect($dto->name)->toBeNull();
    });

    it('hydration profile может централизованно нормализовать пустые строки', function () {
        $dto = EmptyStringProfileNullableDto::from([
            'name' => '',
        ]);

        expect($dto->name)->toBeNull();
    });

    it('Cast имеет приоритет над EmptyStringAsNull', function () {
        $dto = EmptyStringCastPriorityDto::from([
            'name' => '',
        ]);

        expect($dto->name)->toBe('');
    });

    it('EmptyStringAsNull совместим с DefaultValue when Null', function () {
        $dto = EmptyStringDefaultValueDto::from([
            'name' => '',
        ]);

        expect($dto->name)->toBe('unknown');
    });

    it('падает на non-nullable property при EmptyStringAsNull -> null', function () {
        expect(fn () => EmptyStringNonNullableDto::from([
            'name' => '',
        ]))->toThrow(
            HydrationException::class,
            'Invalid data at name',
        );
    });

    it('гидрирует inherited readonly property вне constructor chain', function () {
        $dto = InheritedNullablePayloadDto::from([
            'status' => 'active',
            'result' => [
                'id' => 7,
                'name' => 'Inherited',
            ],
        ]);

        expect($dto->status)->toBe(TestStatus::Active)
            ->and($dto->result)->toBeInstanceOf(NestedItemDto::class)
            ->and($dto->result?->id)->toBe(7)
            ->and($dto->result?->name)->toBe('Inherited');
    });

    it('инициализирует nullable inherited property как null при missing', function () {
        $dto = InheritedNullablePayloadDto::from([
            'status' => 'inactive',
        ]);

        expect($dto->status)->toBe(TestStatus::Inactive)
            ->and($dto->result)->toBeNull();
    });

    it('применяет EmptyStringAsNull в inherited fallback property', function () {
        $dto = InheritedEmptyStringNullableDto::from([
            'status' => 'active',
            'name' => '',
        ]);

        expect($dto->status)->toBe(TestStatus::Active)
            ->and($dto->name)->toBeNull();
    });

    it('падает на missing non-nullable inherited property вне constructor chain', function () {
        expect(fn () => InheritedRequiredPayloadDto::from([
            'status' => 'active',
        ]))->toThrow(
            HydrationException::class,
            'Invalid data at result',
        );
    });
});
