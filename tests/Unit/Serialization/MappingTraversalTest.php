<?php

declare(strict_types=1);

use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\ReceiverOutput;
use ApiSutra\Serialization\Rules\RuleSetCompiler;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Tests\Stubs\MappingExecution\Node;
use ApiSutra\Tests\Support\HydrationRulesFixture;

it('сохраняет отдельные границы DTO, массива, mixed и receiver', function (int $depth): void {
    $dto = new Node();
    $mixed = new Node();
    $array = ['leaf'];
    $input = ['child' => null];
    for ($i = 1; $i < $depth; $i++) {
        $dto = new Node($dto);
        $mixed = new Node([$mixed]);
        $array = [$array];
        $input = ['child' => $input];
    }
    $serializer = new DtoSerializer(new CastRegistry());
    $receiver = new ReceiverOutput(new RuleSetCompiler(new HydrationConfig()));
    foreach ([
        fn () => $serializer->serialize($dto),
        fn () => $serializer->serialize($mixed),
        fn () => $serializer->serialize(new Node($array)),
        fn () => $receiver->contains($array),
        fn () => $receiver->project($array, fn ($child) => $serializer->serialize($child)),
    ] as $operation) {
        if ($depth > 512) {
            expect($operation)->toThrow(SerializationException::class);
        } else {
            // Отрицательное сравнение Pest экспортирует дерево глубиной 512 даже при успехе.
            expect($operation() !== null)->toBeTrue();
        }
        expect($serializer->serialize(new Node('ok')))->toBe(['child' => 'ok']);
    }
    $rules = HydrationRules::create()->withDto(Node::class, DtoRules::create()->field('child', FieldRule::create()->shape(ValueShape::nullable(ValueShape::dto(Node::class)))));
    $hydrator = Hydrator::forRules($rules);
    if ($depth > 512) {
        expect(HydrationRulesFixture::error(fn () => $hydrator->hydrate($input, Node::class))->reason)->toBe('hydration_depth_exceeded');
    } else {
        $count = 0;
        for ($node = $hydrator->hydrate($input, Node::class); $node !== null; $node = $node->child) { $count++; }
        expect($count)->toBe($depth);
    }
    expect($hydrator->hydrate(['child' => null], Node::class)->child)->toBeNull();
})->with([511, 512, 513]);

it('обнаруживает циклы активной ветки и разрешает одинаковых соседей', function (): void {
    $serializer = new DtoSerializer(new CastRegistry());
    $leaf = new Node('shared');
    expect($serializer->serialize(new Node([$leaf, $leaf])))
        ->toBe(['child' => [['child' => 'shared'], ['child' => 'shared']]]);
    $leaf->child = $leaf;
    expect(fn () => $serializer->serialize($leaf))->toThrow(SerializationException::class);
    $leaf->child = 'recovered';
    expect($serializer->serialize($leaf))->toBe(['child' => 'recovered']);
});

it('сохраняет незавершённую ветку фасада после выхода другого Fiber', function (): void {
    $serializer = new DtoSerializer(new CastRegistry());
    $value = new class {
        public function toArray(): array
        {
            if (Fiber::getCurrent() !== null) {
                Fiber::suspend();
            }
            return [];
        }
    };
    $a = new Node($value);
    $b = new Node($value);
    $first = new Fiber(fn () => $serializer->serialize($a));
    $second = new Fiber(fn () => $serializer->serialize($b));
    $first->start();
    $second->start();
    $first->resume();
    expect($serializer->serialize($b))->toBe(['child' => []]);
    $second->resume();
    expect($first->getReturn())->toBe(['child' => []])->and($second->getReturn())->toBe(['child' => []]);
    expect($serializer->serialize(new Node(1)))->toBe(['child' => 1]);
});
