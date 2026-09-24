<?php

declare(strict_types=1);

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydratorInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\ControlFlow\ExecutionCancelledException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Tests\Stubs\Dto\CastStringDto;
use ApiSutra\Tests\Stubs\DtoMapping\CallbackHydrator;
use ApiSutra\Tests\Stubs\DtoMapping\FactoryDto;
use ApiSutra\Tests\Stubs\DtoMapping\NestedFactoryDto;
use ApiSutra\Tests\Stubs\DtoMapping\ShapedFactoryDto;
use ApiSutra\Tests\Stubs\HydrationRules\RecordDto;

it('создаёт private DTO фабрикой и оставляет явную сериализацию штатной', function (): void {
    $custom = new CallbackHydrator(fn ($data) => FactoryDto::create($data['id']), [FactoryDto::class]);
    $hydrator = Hydrator::forConfig(new HydrationConfig(hydrator: $custom));
    $dto = $hydrator->hydrate(['id' => 7], FactoryDto::class);
    expect($dto->id)->toBe(7)->and($dto->toArray())->toBe(['record_id' => 7]);
    expect($hydrator->hydrate(['id' => 8], RecordDto::class)->id)->toBe(8);
    expect(fn () => Hydrator::default()->hydrate(['id' => 7], FactoryDto::class))->toThrow(ConfigurationException::class);
    expect(fn () => $hydrator->hydrate(['id' => 7], FactoryDto::class, hydrator: false))->toThrow(ConfigurationException::class);
});

it('передаёт выбор из native родителя через Nested и Shape к фабричному дочернему DTO', function (): void {
    $custom = new CallbackHydrator(fn ($data) => FactoryDto::create($data['id'] + 1), [FactoryDto::class]);
    $hydrator = Hydrator::forConfig(new HydrationConfig(hydrator: $custom));
    expect($hydrator->hydrate(['child' => ['id' => 7]], NestedFactoryDto::class)->child->id)->toBe(8)
        ->and($hydrator->hydrate(['children' => [['id' => 8]]], ShapedFactoryDto::class)->children[0]->id)->toBe(9);
});

it('передаёт дочерние вызовы через контекст и закрывает каждый контекст', function (): void {
    $contexts = [];
    $custom = new CallbackHydrator(function ($data, $class, HydrationContext $context) use (&$contexts): object {
        $contexts[] = $context;
        expect($context->http())->toBeNull();
        if ($class === NestedFactoryDto::class) {
            $dto = new NestedFactoryDto($context->hydrate($data['child'], FactoryDto::class));
            expect($context->hydrateCollection([['id' => 9]], RecordDto::class)[0]->id)->toBe(9);
            expect($context->http())->toBeNull();
            return $dto;
        }
        return FactoryDto::create($data['id']);
    }, [NestedFactoryDto::class, FactoryDto::class]);
    $dto = Hydrator::forConfig(new HydrationConfig(hydrator: $custom))->hydrate(['child' => ['id' => 7]], NestedFactoryDto::class);
    expect($dto->child->id)->toBe(7)->and($contexts)->toHaveCount(2);
    foreach ($contexts as $context) {
        expect(fn () => $context->http())->toThrow(ConfigurationException::class);
        expect(fn () => $context->hydrate([], RecordDto::class))->toThrow(ConfigurationException::class);
    }
});

it('не применяет профиль и casts поверх пользовательского объекта, но сохраняет native fallback', function (): void {
    $custom = new CallbackHydrator(fn ($data) => new CastStringDto($data['value']), [CastStringDto::class]);
    $hydrator = Hydrator::forConfig(new HydrationConfig(hydrator: $custom));
    expect($hydrator->hydrate(['value' => 'lower'], CastStringDto::class)->value)->toBe('lower')
        ->and($hydrator->hydrate(['value' => 'lower'], CastStringDto::class, hydrator: false)->value)->toBe('LOWER');
    $unsupported = new CallbackHydrator(fn () => throw new LogicException(), []);
    expect($hydrator->hydrate(['value' => 'lower'], CastStringDto::class, hydrator: $unsupported)->value)->toBe('LOWER');
});

it('маскирует чужую ошибку, сохраняет причину и Boundary, не пытается native fallback', function (): void {
    $cause = new LogicException('synthetic-secret');
    $captured = null;
    $custom = new CallbackHydrator(function ($data, $class, $context) use ($cause, &$captured): never {
        $captured = $context;
        throw $cause;
    });
    try {
        Hydrator::forConfig(new HydrationConfig(hydrator: $custom))->hydrate(['id' => 7], RecordDto::class);
        $this->fail('Ожидалась ошибка гидратора');
    } catch (HydrationException $error) {
        expect($error->reason)->toBe('custom_hydrator_failed')->and($error->sourcePathKind)->toBe(SourcePathKind::Boundary)
            ->and($error->getMessage())->not->toContain('synthetic-secret');
        $original = $error;
        while ($original->getPrevious() !== null) {
            $original = $original->getPrevious();
        }
        expect($original)->toBe($cause);
    }
    expect(fn () => $captured->http())->toThrow(ConfigurationException::class);
});

it('проверяет тип результата и guard рекурсивного вызова', function (): void {
    $wrong = new CallbackHydrator(fn () => new stdClass());
    expect(fn () => Hydrator::forConfig(new HydrationConfig(hydrator: $wrong))->hydrate([], RecordDto::class))->toThrow(HydrationException::class);
    $recursive = new CallbackHydrator(fn ($data, $class, $context) => $context->hydrate($data, $class));
    try {
        Hydrator::forConfig(new HydrationConfig(hydrator: $recursive))->hydrate((object) ['id' => 7], RecordDto::class);
        $this->fail('Ожидалась ошибка циклического входа');
    } catch (HydrationException $error) {
        expect($error->reason)->toBe('cyclic_hydration_input');
    }
});

it('не переклассифицирует отмену и истечение бюджета', function (Throwable $failure): void {
    $custom = new CallbackHydrator(fn () => throw $failure);
    expect(fn () => Hydrator::forConfig(new HydrationConfig(hydrator: $custom))->hydrate([], RecordDto::class))->toThrow($failure);
})->with([new ExecutionCancelledException(), new ExecutionDeadlineException('hydrate')]);

it('откладывает native правила при любом глобальном гидраторе без вызова supports при сборке', function (bool $supported): void {
    $rules = HydrationRules::create()->withDto(RecordDto::class, DtoRules::create()
        ->field('missing_property', FieldRule::create()->from('id')));
    $nativeError = null;
    try {
        Hydrator::forRules($rules);
        $this->fail('Неверное свойство должно быть обнаружено штатной компиляцией');
    } catch (ConfigurationException $exception) {
        $nativeError = $exception;
    }
    $custom = new class ($supported) implements DtoHydratorInterface {
        public int $calls = 0;

        public function __construct(private bool $supported)
        {
        }

        public function supports(string $dtoClass): bool
        {
            ++$this->calls;
            return $this->supported;
        }

        public function hydrate(array|object $data, string $dtoClass, HydrationContext $context): object
        {
            return new RecordDto(7);
        }
    };
    $hydrator = Hydrator::forConfig(new HydrationConfig(rules: $rules, hydrator: $custom));
    expect($custom->calls)->toBe(0);
    if ($supported) {
        expect($hydrator->hydrate(['id' => 7], RecordDto::class)->id)->toBe(7);
    } else {
        try {
            $hydrator->hydrate(['id' => 7], RecordDto::class);
            $this->fail('Native fallback обязан проверить свои правила');
        } catch (ConfigurationException $exception) {
            expect($exception->getMessage())->toBe($nativeError->getMessage());
        }
    }
    expect($custom->calls)->toBe(1);
})->with([true, false]);
