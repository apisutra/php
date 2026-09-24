<?php

declare(strict_types=1);

use ApiSutra\Collections\RawCollection;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Tests\Stubs\Collections\JsonValueCollection;
use ApiSutra\Tests\Stubs\Collections\OutputItemCollection;
use ApiSutra\Tests\Stubs\Dto\JsonValue;
use ApiSutra\Tests\Stubs\Dto\OutputItemDto;

describe('Typed collections', function () {
    it('применяет runtime-guard по типу элементов', function () {
        $items = [
            new OutputItemDto(1, 'A'),
            new OutputItemDto(2, 'B'),
        ];

        $collection = new OutputItemCollection($items);

        expect($collection->count())->toBe(2);
    });

    it('выбрасывает исключение на неверный тип', function () {
        expect(fn () => new OutputItemCollection([
            new JsonValue('x'),
        ]))->toThrow(ConfigurationException::class);
    });

    it('поддерживает all/first/count/isEmpty', function () {
        $collection = new OutputItemCollection([
            new OutputItemDto(1, 'A'),
        ]);

        expect($collection->all())->toHaveCount(1)
            ->and($collection->first()?->id)->toBe(1)
            ->and($collection->isEmpty())->toBeFalse();
    });

    it('map возвращает static и сохраняет тип', function () {
        $collection = new OutputItemCollection([
            new OutputItemDto(1, 'A'),
        ]);

        $mapped = $collection->map(
            static fn (OutputItemDto $item): OutputItemDto => new OutputItemDto($item->id, 'B'),
        );

        expect($mapped)->toBeInstanceOf(OutputItemCollection::class)
            ->and($mapped->first()?->label)->toBe('B');
    });

    it('map выбрасывает исключение при неверном типе', function () {
        $collection = new OutputItemCollection([
            new OutputItemDto(1, 'A'),
        ]);

        expect(fn () => $collection->map(
            static fn (OutputItemDto $item): JsonValue => new JsonValue($item->label),
        ))->toThrow(ConfigurationException::class);
    });

    it('map выбрасывает исключение при null результате', function () {
        $collection = new OutputItemCollection([
            new OutputItemDto(1, 'A'),
        ]);

        expect(fn () => $collection->map(
            static fn (OutputItemDto $item): ?OutputItemDto => null,
        ))->toThrow(ConfigurationException::class);
    });

    it('filter возвращает static', function () {
        $collection = new OutputItemCollection([
            new OutputItemDto(1, 'A'),
            new OutputItemDto(2, 'B'),
        ]);

        $filtered = $collection->filter(static fn (OutputItemDto $item): bool => $item->id === 2);

        expect($filtered)->toBeInstanceOf(OutputItemCollection::class)
            ->and($filtered->count())->toBe(1)
            ->and($filtered->first()?->id)->toBe(2);
    });

    it('fromArray и toArray работают для DTO', function () {
        $collection = OutputItemCollection::fromArray([
            new OutputItemDto(1, 'A'),
        ]);

        expect($collection->toArray())->toBe([
            ['id' => 1, 'label' => 'A'],
        ]);
    });

    it('toArray использует JsonSerializable', function () {
        $collection = new JsonValueCollection([
            new JsonValue('x'),
        ]);

        expect($collection->toArray())->toBe([
            ['value' => 'x'],
        ]);
    });

    it('mapToArray возвращает массив', function () {
        $collection = new OutputItemCollection([
            new OutputItemDto(1, 'A'),
            new OutputItemDto(2, 'B'),
        ]);

        expect($collection->mapToArray(static fn (OutputItemDto $item): int => $item->id))
            ->toBe([1, 2]);
    });

    it('contains работает для значения и callback', function () {
        $collection = new OutputItemCollection([
            new OutputItemDto(1, 'A'),
            new OutputItemDto(2, 'B'),
        ]);

        expect($collection->contains($collection->first()))->toBeTrue()
            ->and($collection->contains(static fn (OutputItemDto $item): bool => $item->id === 2))
            ->toBeTrue()
            ->and($collection->contains('id', 2))->toBeTrue()
            ->and($collection->contains('id', '>=', 2))->toBeTrue();
    });

    it('first поддерживает callback и default', function () {
        $collection = new OutputItemCollection([
            new OutputItemDto(1, 'A'),
        ]);

        expect($collection->first(static fn (OutputItemDto $item): bool => $item->id === 2, 'none'))
            ->toBe('none')
            ->and($collection->first(static fn (OutputItemDto $item): bool => $item->id === 1)?->label)
            ->toBe('A');
    });

    it('get возвращает элемент по ключу и default', function () {
        $collection = new OutputItemCollection([
            10 => new OutputItemDto(10, 'A'),
            20 => new OutputItemDto(20, 'B'),
        ]);

        expect($collection->get(10)?->label)->toBe('A')
            ->and($collection->get(30, 'none'))->toBe('none')
            ->and($collection->get(30, static fn (): string => 'lazy'))->toBe('lazy');
    });

    it('has и hasAny проверяют ключи', function () {
        $collection = new OutputItemCollection([
            10 => new OutputItemDto(10, 'A'),
            20 => new OutputItemDto(20, 'B'),
        ]);

        expect($collection->has(10))->toBeTrue()
            ->and($collection->has(10, 20))->toBeTrue()
            ->and($collection->has([10, 30]))->toBeFalse()
            ->and($collection->hasAny(30, 10))->toBeTrue()
            ->and($collection->hasAny([30, 40]))->toBeFalse();
    });

    it('only и except фильтруют по ключам', function () {
        $collection = new OutputItemCollection([
            10 => new OutputItemDto(10, 'A'),
            20 => new OutputItemDto(20, 'B'),
            30 => new OutputItemDto(30, 'C'),
        ]);

        expect(array_keys($collection->only(10, 30)->all()))->toBe([10, 30])
            ->and(array_keys($collection->except(20)->all()))->toBe([10, 30]);
    });

    it('firstWhere находит элемент по полю', function () {
        $collection = new OutputItemCollection([
            new OutputItemDto(1, 'A'),
            new OutputItemDto(2, 'B'),
        ]);

        expect($collection->firstWhere('id', 2)?->label)->toBe('B');
    });

    it('pluck возвращает список значений', function () {
        $collection = new OutputItemCollection([
            new OutputItemDto(1, 'A'),
            new OutputItemDto(2, 'B'),
        ]);

        expect($collection->pluck('label'))
            ->toBeInstanceOf(RawCollection::class)
            ->and($collection->pluck('label')->all())->toBe([
            0 => 'A',
            1 => 'B',
        ]);
    });

    it('keyBy индексирует по ключу', function () {
        $collection = new OutputItemCollection([
            new OutputItemDto(10, 'A'),
            new OutputItemDto(20, 'B'),
        ]);

        $keyed = $collection->keyBy('id')->all();

        expect(array_keys($keyed))->toBe([10, 20])
            ->and($keyed[10]->label)->toBe('A');
    });

    it('sortBy сортирует по ключу', function () {
        $collection = new OutputItemCollection([
            new OutputItemDto(2, 'B'),
            new OutputItemDto(1, 'A'),
        ]);

        $sorted = $collection->sortBy('id');

        expect($sorted->values()->pluck('id')->all())->toBe([1, 2]);
    });

    it('sortByDesc сортирует по убыванию', function () {
        $collection = new OutputItemCollection([
            new OutputItemDto(2, 'B'),
            new OutputItemDto(1, 'A'),
        ]);

        $sorted = $collection->sortByDesc('id');

        expect($sorted->values()->pluck('id')->all())->toBe([2, 1]);
    });

    it('unique возвращает уникальные элементы', function () {
        $collection = new OutputItemCollection([
            new OutputItemDto(1, 'A'),
            new OutputItemDto(1, 'A'),
        ]);

        $unique = $collection->unique('id');

        expect($unique->count())->toBe(1);
    });

    it('values сбрасывает ключи', function () {
        $collection = new OutputItemCollection([
            10 => new OutputItemDto(10, 'A'),
            20 => new OutputItemDto(20, 'B'),
        ]);

        expect(array_keys($collection->values()->all()))->toBe([0, 1]);
    });
});

describe('RawCollection', function () {
    it('не применяет guard и принимает mixed', function () {
        $collection = new RawCollection([1, 'a', new JsonValue('x')]);

        expect($collection->count())->toBe(3)
            ->and($collection->first())->toBe(1);
    });

    it('toArray сериализует JsonSerializable', function () {
        $collection = new RawCollection([new JsonValue('x')]);

        expect($collection->toArray())->toBe([
            ['value' => 'x'],
        ]);
    });

    it('toArray использует toArray у объектов', function () {
        $collection = new RawCollection([new class {
            /**
             * @return array<string, string>
             */
            public function toArray(): array
            {
                return ['value' => 'ok'];
            }
        }]);

        expect($collection->toArray())->toBe([
            ['value' => 'ok'],
        ]);
    });
});
