<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Contracts\Interfaces\Casting\SerializationCastInterface;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Serialization\Context\SerializationContext;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Tests\Stubs\MappingExecution\OutputDto;
use ApiSutra\Tests\Stubs\MappingExecution\OutputHandler;
use ApiSutra\Tests\Stubs\MappingSerialization\Argument;
use ApiSutra\Tests\Stubs\MappingSerialization\ArgumentDto;
use ApiSutra\Tests\Stubs\MappingSerialization\EffectDto;
use ApiSutra\Tests\Stubs\MappingSerialization\FieldsDto;
use ApiSutra\Tests\Stubs\MappingSerialization\LiveProfile;
use ApiSutra\Tests\Stubs\MappingSerialization\MutableFields;

beforeEach(function (): void {
    LiveProfile::$current = new DtoSerializationPolicy();
    LiveProfile::$handlers = LiveProfile::$trace = [];
    OutputHandler::$action = null;
    OutputHandler::$constructed = 0;
    Argument::$created = 0;
    Argument::$fail = false;
    Argument::$last = null;
});
afterEach(function (): void {
    LiveProfile::$handlers = LiveProfile::$trace = [];
    OutputHandler::$action = null;
    Argument::$fail = false;
    Argument::$last = null;
});

it('перепривязывает policy и общий handler профиля при повторном исполнении плана', function (bool $enabled): void {
    $serializer = new DtoSerializer(new CastRegistry(), new AttributeMetadataCache($enabled));
    $dto = new FieldsDto(createdAt: new DateTimeImmutable('2025-03-04T00:00:00Z'));
    expect($serializer->serialize($dto))->toBe(['record' => ['id' => 7], 'createdAt' => '2025']);
    $handler = new class implements SerializationCastInterface {
        public int $calls = 0;
        public function serialize(mixed $value, SerializationContext $context): mixed
        {
            return $value + ++$this->calls;
        }
    };
    LiveProfile::$current = new DtoSerializationPolicy(namingStrategy: NamingStrategy::SnakeCase, serializeNulls: true);
    LiveProfile::$handlers = ['int' => $handler];
    expect($serializer->serialize($dto))->toBe([
        'record' => ['id' => 8], 'child' => null, 'created_at' => '2025', 'optional' => null,
    ])->and($serializer->serialize($dto)['record']['id'])->toBe(9)
        ->and($handler->calls)->toBe(2)
        ->and(LiveProfile::$trace)->toBe(['policy', 'casts', 'policy', 'casts', 'policy', 'casts']);
})->with([false, true]);

it('один кеш не переносит binding DX и явной policy между фасадами и потомками', function (bool $enabled): void {
    $cache = new AttributeMetadataCache($enabled);
    $first = new DtoSerializer(new CastRegistry(), $cache);
    $second = new DtoSerializer(new CastRegistry(), $cache);
    $dto = new FieldsDto(child: new FieldsDto());
    LiveProfile::$current = new DtoSerializationPolicy(serializeNulls: true);
    expect($first->serialize($dto)['child'])->toHaveKeys(['child', 'createdAt', 'optional']);
    $policy = new DtoSerializationPolicy(namingStrategy: NamingStrategy::SnakeCase);
    expect($second->serializeWithPolicy($dto, $policy))->toBe([
        'record' => ['id' => 7], 'child' => ['record' => ['id' => 7]],
    ])->and(LiveProfile::$trace)->toBe(['policy', 'casts', 'policy', 'casts']);
    LiveProfile::$current = new DtoSerializationPolicy();
    expect($first->serialize($dto))->toBe(['record' => ['id' => 7], 'child' => ['record' => ['id' => 7]]]);
})->with([false, true]);

it('выбирает runtime union и не применяет type cast раньше DTO и массива', function (bool $enabled): void {
    $serializer = new DtoSerializer(new CastRegistry(), new AttributeMetadataCache($enabled));
    $casts = new CastRegistry();
    $calls = [];
    $handler = new class ($calls) implements SerializationCastInterface {
        /** @param list<string> $calls */
        public function __construct(public array &$calls)
        {
        }
        public function serialize(mixed $value, SerializationContext $context): mixed
        {
            $this->calls[] = get_debug_type($value);
            return 'cast';
        }
    };
    $casts->register('string', $handler);
    $casts->register('mixed', $handler);
    $policy = new DtoSerializationPolicy();
    expect($serializer->serializeWithPolicy(new FieldsDto('7', [1, 2]), $policy, casts: $casts))
        ->toBe(['record' => ['id' => 'cast'], 'child' => [1, 2]]);
    expect($serializer->serializeWithPolicy(new FieldsDto(7, new FieldsDto()), $policy, casts: $casts))
        ->toBe(['record' => ['id' => 7], 'child' => ['record' => ['id' => 7]]]);
    expect($serializer->serializeWithPolicy(new FieldsDto(7, 'text'), $policy, casts: $casts))
        ->toBe(['record' => ['id' => 7], 'child' => 'cast'])
        ->and($calls)->toBe(['string', 'string']);
})->with([false, true]);

it('читает следующее поле и изменённый registry после callback предыдущего поля', function (bool $enabled): void {
    $serializer = new DtoSerializer(new CastRegistry(), new AttributeMetadataCache($enabled));
    $dto = new MutableFields();
    $casts = new CastRegistry();
    $handler = new class implements SerializationCastInterface {
        public function serialize(mixed $value, SerializationContext $context): mixed
        {
            return 'selected:' . $value;
        }
    };
    OutputHandler::$action = function ($value) use ($dto, $casts, $handler) {
        $dto->number = 'changed';
        $casts->register('string', $handler);
        return $value;
    };
    for ($i = 0; $i < 2; $i++) {
        $dto->number = 1;
        expect($serializer->serializeWithPolicy($dto, new DtoSerializationPolicy(), casts: $casts))
            ->toBe(['run' => true, 'number' => 'selected:changed']);
    }
})->with([false, true]);

it('выполняет projection после всех array callbacks и сохраняет исходный plain объект', function (bool $enabled): void {
    $serializer = new DtoSerializer(new CastRegistry(), new AttributeMetadataCache($enabled), config: new HydrationConfig());
    $plain = new stdClass();
    $plain->child = null;
    OutputHandler::$action = function ($value) use ($plain) {
        $plain->child = new OutputDto(7, ['secret' => 'kept in source']);
        return $value;
    };
    $dto = new FieldsDto(child: [$plain, new EffectDto()]);
    $result = $serializer->serialize($dto);
    expect($result['child'][0])->toBeInstanceOf(stdClass::class)
        ->and($result['child'][0])->not->toBe($plain)
        ->and($result['child'][0]->child)->toBe(['value' => 7])
        ->and($result['child'][1])->toBe(['run' => true])
        ->and($plain->child->_extra)->toBe(['secret' => 'kept in source']);
})->with([false, true]);

it('не удерживает args в плане и повторяет new при null, ошибке и следующем корне', function (bool $enabled): void {
    $serializer = new DtoSerializer(new CastRegistry(), new AttributeMetadataCache($enabled));
    expect($serializer->serialize(new ArgumentDto()))->toBe([])
        ->and(Argument::$created)->toBe(1)->and(Argument::$last?->get())->toBeNull();
    Argument::$fail = true;
    expect(fn () => $serializer->serialize(new ArgumentDto()))->toThrow(RuntimeException::class, 'argument failure');
    Argument::$fail = false;
    expect($serializer->serialize(new ArgumentDto(0)))->toBe(['value' => 1])
        ->and($serializer->serialize(new ArgumentDto(0)))->toBe(['value' => 1])
        ->and(Argument::$created)->toBe(4)->and(Argument::$last?->get())->toBeNull();
})->with([false, true]);
