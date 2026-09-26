<?php

declare(strict_types=1);

use ApiSutra\Serialization\Input\JsonDecoder;
use ApiSutra\Serialization\Input\HydrationInput;
use ApiSutra\Serialization\Input\JsonShapeReader;
use ApiSutra\Serialization\Input\SourceShapeMap;
use ApiSutra\Serialization\Rules\InputShape;
use Random\Engine\Mt19937;
use Random\Randomizer;

function jsonShapeOracle(mixed $value): ?SourceShapeMap
{
    if (!is_array($value) && !$value instanceof stdClass) {
        return null;
    }
    $children = [];
    foreach ((array) $value as $key => $child) {
        $shape = jsonShapeOracle($child);
        if ($shape !== null) {
            $children[$key] = $shape;
        }
    }
    return new SourceShapeMap(is_array($value) ? InputShape::List : InputShape::Object, $children);
}

function assertJsonInputShape(HydrationInput $input, mixed $value): void
{
    if (!is_array($value) && !$value instanceof stdClass) {
        expect($input->kind())->toBeNull();
        return;
    }
    expect($input->kind())->toBe(is_array($value) ? InputShape::List : InputShape::Object);
    foreach ((array) $value as $key => $child) {
        assertJsonInputShape(new HydrationInput(
            $input->value[$key],
            $input->shape?->select([$key]),
            $input->jsonSourceKnown,
        ), $child);
    }
}

it('совпадает с C decoder на строках escapes Unicode и повторяющихся ключах', function (string $json): void {
    $expected = json_decode($json, false, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
    $input = (new JsonDecoder())->decode($json);
    expect((new JsonShapeReader())->read($json))->toEqual(jsonShapeOracle($expected))
        ->and($input->value)->toBe(json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING));
    assertJsonInputShape($input, $expected);
})->with([
    '{}', '[]', 'null', 'false', '0', json_encode('{["x"]}', JSON_THROW_ON_ERROR),
    '{"0":{},"1":[],"01":{},"":[],"/~":[{},[]]}',
    '{"\\u0061":{},"a":[],"b":[{}],"b":0,"c":{"x":{}},"c":{"y":[]}}',
    json_encode(['a"b' => ['{\\}', new stdClass(), [[], new stdClass()]], 'emoji' => ['😀' => []]], JSON_THROW_ON_ERROR),
    '[1,2.3,-4e5,true,false,null,"x",{},[],{"huge":9999999999999999999999}]',
    str_repeat('{"x":', 200) . '[]' . str_repeat('}', 200),
]);

it('сверяет воспроизводимые комбинации контейнеров с независимым decoder', function (): void {
    $random = new Randomizer(new Mt19937(5801));
    $build = function (int $depth) use (&$build, $random): mixed {
        $kind = $random->getInt(0, $depth > 0 ? 3 : 1);
        if ($kind < 2) {
            return [null, false, 0, '"\\{}[]😀'][ $random->getInt(0, 3) ];
        }
        $values = [];
        $keys = ['0', '1', '01', '', '/', '~', '😀', '"\\'];
        for ($i = $random->getInt(0, 5); $i > 0; $i--) {
            $value = $build($depth - 1);
            if ($kind === 2) {
                $values[] = $value;
            } else {
                $values[$keys[$random->getInt(0, count($keys) - 1)]] = $value;
            }
        }
        return $kind === 2 ? $values : (object) $values;
    };
    for ($i = 0; $i < 100; $i++) {
        $json = json_encode($build(5), JSON_THROW_ON_ERROR);
        expect((new JsonShapeReader())->read($json))
            ->toEqual(jsonShapeOracle(json_decode($json, false, 512, JSON_THROW_ON_ERROR)));
        assertJsonInputShape((new JsonDecoder())->decode($json), json_decode($json, false, 512, JSON_THROW_ON_ERROR));
    }
});

it('принимает ведущий NUL и не отключает формы соседнего узла', function (): void {
    $json = '{"\\u0000key":{},"items":{"0":"a","1":"b"},"array":[]}';
    $input = (new JsonDecoder())->decode($json);
    expect($input->shape->kind)->toBe(InputShape::Object)
        ->and($input->shape->select(["\0key"])->kind)->toBe(InputShape::Object)
        ->and($input->select('items')->shape->kind)->toBe(InputShape::Object)
        ->and($input->select('array')->shape->kind)->toBe(InputShape::List);

});

it('не создаёт карту без запроса формы и не принимает синтаксически неверный JSON', function (): void {
    expect((new JsonDecoder())->decode('{"items":{}}', false)->shape)->toBeNull()
        ->and((new JsonDecoder())->decode('{"items":{}}', false)->jsonSourceKnown)->toBeFalse();
    expect(fn () => (new JsonDecoder())->decode('{"items":['))->toThrow(JsonException::class);
});

it('отделяет отсутствие отметок от неизвестного происхождения и сохраняет копирование значений', function (): void {
    $input = (new JsonDecoder())->decode('{"items":[{"nested":{"value":1},"tags":[]}]}');
    expect($input->shape)->toBeNull()->and($input->kind())->toBe(InputShape::Object)
        ->and($input->select('items')->kind())->toBe(InputShape::List)
        ->and(new HydrationInput($input->value)->kind())->toBeNull();
    $copy = $input->value;
    $copy['items'][0]['nested']['value'] = 99;
    $copy['items'][0]['tags'][] = 'new';
    expect($input->value['items'][0])->toBe(['nested' => ['value' => 1], 'tags' => []]);
});

it('не удерживает соседние метаданные после отделения поддерева', function (): void {
    $input = (new JsonDecoder())->decode('{"data":{"child":{}},"unrelated":[{},{}]}');
    $root = WeakReference::create($input->shape);
    $unrelated = WeakReference::create($input->shape->children['unrelated']);
    $selected = $input->select('data');
    unset($input);
    expect($root->get())->toBeNull()->and($unrelated->get())->toBeNull()
        ->and($selected->select('child')->kind())->toBe(InputShape::Object);
});

it('проверяет всё тело повторным decode после ошибки непредставимого имени', function (): void {
    expect(fn () => (new JsonDecoder())->decode('{"\\u0000key":{},"items":['))->toThrow(JsonException::class);
});
