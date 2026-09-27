<?php

declare(strict_types=1);

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Tests\Stubs\JsonContainerShapes\Envelope;
use ApiSutra\Tests\Stubs\JsonContainerShapes\Node;
use ApiSutra\Tests\Stubs\PolymorphicInput\DictionaryCast;
use ApiSutra\Tests\Stubs\PolymorphicInput\DictionaryDto;
use ApiSutra\Tests\Support\HydrationRulesFixture as Fixture;

it('гидратирует исходный JSON публичным входом с формой корня и дочерних значений', function (): void {
    $hydrator = Hydrator::default();
    expect($hydrator->hydrateJson('{}', Node::class))->toBeInstanceOf(Node::class);
    foreach (['[]', '["lost"]', 'null', '42', '"text"', 'false'] as $json) {
        expect(Fixture::error(fn () => $hydrator->hydrateJson($json, Node::class))->reason)->toBe('invalid_object_shape');
    }
    expect($hydrator->hydrateJson('{"items":[],"payload":{}}', Envelope::class)->items)->toBe([]);
    foreach (['{}', '{"0":"a","1":"b"}'] as $items) {
        $error = Fixture::error(fn () => $hydrator->hydrateJson('{"items":' . $items . ',"payload":{}}', Envelope::class));
        expect($error->reason)->toBe('invalid_list_shape')->and($error->sourcePath)->toBe('/items');
    }
    expect($hydrator->hydrateJson('{"name":"first","name":"last","\\u0000key":{}}', Node::class)->name)->toBe('last');
});

it('отличает ошибку JSON от ошибки формы и не включает метаданные при false', function (): void {
    foreach (['', '{broken'] as $json) {
        $error = Fixture::error(fn () => Hydrator::default()->hydrateJson($json, Node::class));
        expect($error->reason)->toBe('invalid_json')->and($error->getPrevious())->toBeInstanceOf(JsonException::class);
    }
    $hydrator = Hydrator::forConfig(new HydrationConfig(jsonShapeValidation: false));
    expect($hydrator->hydrateJson('[]', Node::class))->toBeInstanceOf(Node::class);
    expect(Fixture::error(fn () => $hydrator->hydrateJson('["lost"]', Node::class))->reason)->toBe('invalid_object_shape');
});

it('проверяет InputShape до cast с точным From путём и прежним порядком missing null', function (): void {
    DictionaryCast::$calls = 0;
    $hydrator = Hydrator::default();
    foreach (['{}' => 'required_field_missing', '{"data":{"members":null}}' => 'explicit_null_not_allowed', '{"data":{"members":[]}}' => 'invalid_object_shape'] as $json => $reason) {
        $error = Fixture::error(fn () => $hydrator->hydrateJson($json, DictionaryDto::class));
        expect($error->reason)->toBe($reason)->and($error->sourcePath)->toBe('/data/members');
    }
    expect(DictionaryCast::$calls)->toBe(0);
    expect($hydrator->hydrateJson('{"data":{"members":{}}}', DictionaryDto::class)->participants)->toBe([]);
    expect($hydrator->hydrateJson('{"data":{"members":{"0":"a","01":"b"}}}', DictionaryDto::class)->participants)->toBe([0 => 'a', '01' => 'b']);
    expect(DictionaryCast::$calls)->toBe(2);
    expect(Hydrator::forConfig(new HydrationConfig(jsonShapeValidation: false))->hydrateJson('{"data":{"members":[]}}', DictionaryDto::class)->participants)->toBe([]);
});

it('не смешивает внешний FieldRule с атрибутом входной формы', function (): void {
    expect(fn () => Hydrator::forRules(HydrationRules::create()->withDto(
        DictionaryDto::class,
        DtoRules::create()->field('participants', FieldRule::create()->required()),
    )))->toThrow(ConfigurationException::class);
});
