<?php

declare(strict_types=1);

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydratorInterface;
use ApiSutra\DataTransfer\AbstractDto;
use ApiSutra\Enums\DataTransfer\UnknownVariant;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use ApiSutra\Tests\Stubs\JsonContainerShapes\Node;
use ApiSutra\Tests\Stubs\PolymorphicInput\BaseEvent;
use ApiSutra\Tests\Stubs\PolymorphicInput\ChildEvent;
use ApiSutra\Tests\Stubs\PolymorphicInput\Event;
use ApiSutra\Tests\Stubs\PolymorphicInput\ExternalEvent;
use ApiSutra\Tests\Stubs\PolymorphicInput\ExternalOne;
use ApiSutra\Tests\Stubs\PolymorphicInput\ExternalTwo;
use ApiSutra\Tests\Stubs\PolymorphicInput\LeafEvent;
use ApiSutra\Tests\Stubs\PolymorphicInput\MessageEvent;
use ApiSutra\Tests\Stubs\PolymorphicInput\RawEvent;
use ApiSutra\Tests\Support\HydrationRulesFixture as Fixture;

it('выбирает варианты объявленного типа без регистрации и сохраняет формы рекурсивных узлов', function (): void {
    $hydrator = Hydrator::default();
    $dto = $hydrator->hydrateJson('{"type":"message","text":"first","next":{"type":"message","next":{"type":"future","x":1}}}', Event::class);
    expect($dto)->toBeInstanceOf(MessageEvent::class)->and($dto->next)->toBeInstanceOf(MessageEvent::class)
        ->and($dto->next->next)->toBeInstanceOf(RawEvent::class)->and($dto->next->next->raw)->toBe(['type' => 'future', 'x' => 1]);
    $error = Fixture::error(fn () => $hydrator->hydrateJson('{"type":"message","next":{"type":"message","permissions":{}}}', Event::class));
    expect($error->reason)->toBe('invalid_list_shape')->and($error->path)->toBe('next.permissions')->and($error->sourcePath)->toBe('/next/permissions');
    expect($hydrator->hydrateJson('{}', Event::class))->toBeInstanceOf(RawEvent::class);
});

it('делает один выбор на узел для self fallback и выбранного подтипа с собственной декларацией', function (): void {
    $hydrator = Hydrator::default();
    expect($hydrator->hydrateJson('{"type":"future"}', BaseEvent::class)::class)->toBe(BaseEvent::class)
        ->and($hydrator->hydrateJson('{"type":"child"}', BaseEvent::class)::class)->toBe(ChildEvent::class)
        ->and($hydrator->hydrateJson('{"second":"leaf"}', ChildEvent::class)::class)->toBe(LeafEvent::class)
        ->and($hydrator->hydrateJson('{}', LeafEvent::class)::class)->toBe(LeafEvent::class);
});

it('изолирует внешние варианты по конфигурации и не меняет исходный набор правил', function (): void {
    $rules = HydrationRules::create();
    $one = Hydrator::forRules($rules->withVariants(ExternalEvent::class, 'type', ['x' => ExternalOne::class]));
    $two = Hydrator::forRules($rules->withVariants(ExternalEvent::class, 'type', ['x' => ExternalTwo::class]));
    for ($i = 0; $i < 3; $i++) {
        expect($one->hydrateJson('{"type":"x"}', ExternalEvent::class))->toBeInstanceOf(ExternalOne::class)
            ->and($two->hydrateJson('{"type":"x"}', ExternalEvent::class))->toBeInstanceOf(ExternalTwo::class);
    }
    expect(fn () => Hydrator::forRules($rules)->hydrateJson('{}', ExternalEvent::class))->toThrow(ConfigurationException::class);
    expect(Fixture::error(fn () => $one->hydrateJson('{}', ExternalEvent::class))->reason)->toBe('unknown_nested_variant');
});

it('проверяет всю map и типовую unknown политику без конструктора supports или DI', function (): void {
    foreach ([UnknownVariant::KeepRaw, UnknownVariant::Skip, Node::class] as $unknown) {
        $hydrator = Hydrator::forRules(HydrationRules::create()->withVariants(ExternalEvent::class, 'type', ['x' => ExternalOne::class], unknown: $unknown));
        expect(fn () => $hydrator->hydrateJson('{"type":"x"}', ExternalEvent::class))->toThrow(ConfigurationException::class);
    }
    foreach (['Missing\\Dto', Node::class] as $invalid) {
        $hydrator = Hydrator::forRules(HydrationRules::create()->withVariants(ExternalEvent::class, 'type', ['x' => ExternalOne::class, 'unused' => $invalid]));
        expect(fn () => $hydrator->hydrateJson('{"type":"x"}', ExternalEvent::class))->toThrow(ConfigurationException::class);
    }
    $hydrator = Hydrator::forRules(HydrationRules::create()->withVariants(Event::class, 'type', ['message' => MessageEvent::class]));
    expect(fn () => $hydrator->hydrateJson('{}', Event::class))->toThrow(ConfigurationException::class);
});

it('передаёт custom гидратору выбранный класс а проверка схемы его не вызывает', function (): void {
    $custom = new class implements DtoHydratorInterface {
        public array $seen = [];
        public function supports(string $dtoClass): bool
        {
            $this->seen[] = $dtoClass;
            return $dtoClass === MessageEvent::class;
        }
        public function hydrate(array|object $data, string $dtoClass, HydrationContext $context): object
        {
            return new MessageEvent('custom');
        }
    };
    $hydrator = Hydrator::forConfig(new HydrationConfig(hydrator: $custom));
    $hydrator->descriptions()->targets()->validate(Event::class);
    expect($custom->seen)->toBe([]);
    expect($hydrator->hydrateJson('{"type":"message"}', Event::class)->text)->toBe('custom');
    expect($custom->seen)->toBe([MessageEvent::class]);
    expect($hydrator->hydrateJson('{"type":"new"}', Event::class))->toBeInstanceOf(RawEvent::class);
});

it('выбор вариантов не расходует дополнительные уровни глубины данных', function (): void {
    $data = ['type' => 'message'];
    for ($i = 0; $i < 300; $i++) {
        $data = ['type' => 'message', 'next' => $data];
    }
    expect(Hydrator::default()->hydrate($data, Event::class))->toBeInstanceOf(MessageEvent::class);
});

it('разрешает abstract назначение но запрещает abstract класс внутри map', function (): void {
    $rules = HydrationRules::create()->withVariants(AbstractDto::class, 'type', ['simple' => SimpleResponseDto::class]);
    $dto = Hydrator::forRules($rules)->hydrateJson('{"type":"simple","id":7,"name":"ready"}', AbstractDto::class);
    expect($dto)->toBeInstanceOf(SimpleResponseDto::class)->and($dto->id)->toBe(7);
    $invalid = HydrationRules::create()->withVariants(AbstractDto::class, 'type', ['simple' => SimpleResponseDto::class, 'unused' => AbstractDto::class]);
    expect(fn () => Hydrator::forRules($invalid)->hydrateJson('{"type":"simple","id":7,"name":"ready"}', AbstractDto::class))
        ->toThrow(ConfigurationException::class);
});
