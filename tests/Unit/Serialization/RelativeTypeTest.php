<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\HandlerSpec;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\SerializationValueResolver;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\MappingExecution\InputHandler;
use ApiSutra\Tests\Stubs\MappingExecution\OutputHandler;
use ApiSutra\Tests\Stubs\RelativeTypes\BaseRecord;
use ApiSutra\Tests\Stubs\RelativeTypes\InheritedParent;
use ApiSutra\Tests\Stubs\RelativeTypes\InheritedSelf;
use ApiSutra\Tests\Stubs\RelativeTypes\InheritedTrait;
use ApiSutra\Tests\Stubs\RelativeTypes\NamedNode;
use ApiSutra\Tests\Stubs\RelativeTypes\NamedParentLink;
use ApiSutra\Tests\Stubs\RelativeTypes\NamedUnionNode;
use ApiSutra\Tests\Stubs\RelativeTypes\NestedSelf;
use ApiSutra\Tests\Stubs\RelativeTypes\NodeRequest;
use ApiSutra\Tests\Stubs\RelativeTypes\OtherTraitNode;
use ApiSutra\Tests\Stubs\RelativeTypes\ParentLink;
use ApiSutra\Tests\Stubs\RelativeTypes\PlainSelf;
use ApiSutra\Tests\Stubs\RelativeTypes\SelfNode;
use ApiSutra\Tests\Stubs\RelativeTypes\SelfRequest;
use ApiSutra\Tests\Stubs\RelativeTypes\TraitNode;
use ApiSutra\Tests\Stubs\RelativeTypes\UnionNode;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Pipeline\PipelineContext;

dataset('relative policies', [
    [false, ScalarPolicy::Legacy], [true, ScalarPolicy::Legacy],
    [false, ScalarPolicy::Strict], [true, ScalarPolicy::Strict],
]);

function relativeHydrator(bool $cache, ScalarPolicy $mode): Hydrator
{
    return new Hydrator(new CastRegistry(), new AttributeMetadataCache($cache), config: new HydrationConfig(
        policy: new RulePolicy(scalars: $mode),
    ));
}

afterEach(function (): void {
    InputHandler::$action = OutputHandler::$action = null;
});

it('гидратирует self как именованный рекурсивный DTO и сохраняет область наследуемого объявления', function (bool $cache, ScalarPolicy $mode): void {
    $hydrator = relativeHydrator($cache, $mode);
    $data = ['child' => ['id' => 8, 'child' => ['id' => 9]]];
    for ($i = 0; $i < 2; $i++) {
        $self = $hydrator->hydrate($data, SelfNode::class);
        $named = $hydrator->hydrate($data, NamedNode::class);
        $inherited = $hydrator->hydrate($data, InheritedSelf::class);
        expect($self->child::class)->toBe(SelfNode::class)
            ->and($self->child->child::class)->toBe(SelfNode::class)
            ->and($self->toArray())->toBe($named->toArray())
            ->and($inherited->child::class)->toBe(SelfNode::class)
            ->and($hydrator->hydrate([], SelfNode::class)->child)->toBeNull()
            ->and($hydrator->hydrate(['child' => null], SelfNode::class)->child)->toBeNull();
    }
})->with('relative policies');

it('разрешает parent от объявления, а не от текущего наследника', function (bool $cache, ScalarPolicy $mode): void {
    $hydrator = relativeHydrator($cache, $mode);
    foreach ([ParentLink::class, InheritedParent::class] as $class) {
        $data = ['link' => ['id' => 9], 'id' => 8];
        $dto = $hydrator->hydrate($data, $class);
        expect($dto->link::class)->toBe(BaseRecord::class)
            ->and($dto->toArray())->toBe($hydrator->hydrate($data, NamedParentLink::class)->toArray());
    }
})->with('relative policies');

it('использует область потребителя trait и исходного класса после наследования', function (bool $cache, ScalarPolicy $mode): void {
    $hydrator = relativeHydrator($cache, $mode);
    foreach ([TraitNode::class => TraitNode::class, OtherTraitNode::class => OtherTraitNode::class, InheritedTrait::class => TraitNode::class] as $class => $target) {
        $dto = $hydrator->hydrate(['child' => ['child' => null]], $class);
        expect($dto->child::class)->toBe($target)->and($dto->child->child)->toBeNull();
    }
})->with('relative policies');

it('сохраняет scalar и null ветви union при разрешении class ветви', function (bool $cache, ScalarPolicy $mode): void {
    $hydrator = relativeHydrator($cache, $mode);
    foreach ([[], ['child' => []], '7', 'text', null] as $value) {
        expect($hydrator->hydrate(['child' => $value], UnionNode::class)->toArray())
            ->toBe($hydrator->hydrate(['child' => $value], NamedUnionNode::class)->toArray());
    }
    expect($hydrator->hydrate(['child' => new UnionNode('ready')], UnionNode::class)->child->child)->toBe('ready');
})->with('relative policies');

it('сохраняет plain границу и уже работающий Nested для self', function (bool $cache, ScalarPolicy $mode): void {
    $hydrator = relativeHydrator($cache, $mode);
    expect(fn () => $hydrator->hydrate(['child' => []], PlainSelf::class))->toThrow(HydrationException::class);
    $child = new PlainSelf();
    expect($hydrator->hydrate(['child' => $child], PlainSelf::class)->child)->toBe($child)
        ->and($hydrator->hydrate(['child' => []], NestedSelf::class)->child)->toBeInstanceOf(NestedSelf::class);
})->with('relative policies');

it('доставляет ошибки вложенного self с тем же путём и восстанавливает корень после цикла', function (bool $cache, ScalarPolicy $mode): void {
    $hydrator = relativeHydrator($cache, $mode);
    foreach ([SelfNode::class, NamedNode::class] as $class) {
        try {
            $hydrator->hydrate(['child' => ['id' => 'invalid']], $class);
            test()->fail('Ожидалась ошибка вложенного id');
        } catch (HydrationException $e) {
            expect($e->reason)->toBe('invalid_field_type')->and($e->path)->toBe('child.id')
                ->and($e->sourcePath)->toBe('/child/id');
        }
        $input = new stdClass();
        $input->child = $input;
        try {
            $hydrator->hydrate($input, $class);
            test()->fail('Ожидалась ошибка цикла');
        } catch (HydrationException $e) {
            expect($e->reason)->toBe('cyclic_hydration_input')->and($e->path)->toBe('child');
        }
        expect($hydrator->hydrate(['child' => []], $class)->child)->toBeInstanceOf($class);
    }
})->with('relative policies');

it('применяет class-key input cast к self и сохраняет класс готового plain значения', function (bool $cache): void {
    InputHandler::$action = static fn (array $value): PlainSelf => new PlainSelf(id: $value['id']);
    $hydrator = new Hydrator(new CastRegistry(), new AttributeMetadataCache($cache), config: new HydrationConfig(
        policy: new RulePolicy(casts: [PlainSelf::class => new HandlerSpec(InputHandler::class)]),
    ));
    expect($hydrator->hydrate(['child' => ['id' => 11]], PlainSelf::class)->child->id)->toBe(11);
})->with([false, true]);

it('применяет output class-key cast в плане и прямом resolver без псевдотипа self', function (bool $cache): void {
    OutputHandler::$action = static fn (): string => 'converted';
    $casts = new CastRegistry();
    $casts->register(PlainSelf::class, new OutputHandler());
    $serializer = new DtoSerializer(new CastRegistry(), new AttributeMetadataCache($cache));
    $child = new PlainSelf();
    $policy = new DtoSerializationPolicy();
    $profile = new class implements DtoSerializationProfileInterface {
        public function policy(): DtoSerializationPolicy
        {
            return new DtoSerializationPolicy();
        }
        public function casts(): array
        {
            return [PlainSelf::class => OutputHandler::class];
        }
    };
    $context = new PipelineContext(new NodeRequest(), new ClientConfig(baseUrl: 'https://relative.test', dtoSerializationProfile: $profile), 'relative-dx');
    expect($serializer->serialize(new PlainSelf($child), $context)['child'])->toBe('converted');
    for ($i = 0; $i < 2; $i++) {
        expect($serializer->serializeWithPolicy(new PlainSelf($child), $policy, casts: $casts)['child'])->toBe('converted');
    }
    expect((new SerializationValueResolver($casts))->resolve(
        $child, null, null, new ReflectionProperty(PlainSelf::class, 'child'), null, $policy,
        fn (object $dto): array => $serializer->serialize($dto),
    ))->toBe('converted');
})->with([false, true]);

it('использует self class-key в query и body плана запроса', function (bool $cache): void {
    OutputHandler::$action = static fn (): string => 'converted';
    $casts = new CastRegistry();
    $casts->register(SelfRequest::class, new OutputHandler());
    $serializer = new Serializer($casts, new AttributeMetadataCache($cache));
    $request = new SelfRequest(query: new SelfRequest(), body: new SelfRequest());
    $context = new PipelineContext($request, new ClientConfig(baseUrl: 'https://relative.test'), 'relative');
    for ($i = 0; $i < 2; $i++) {
        $prepared = $serializer->serialize($request, $context);
        expect($prepared->url)->toBe('https://relative.test/node?query=converted')
            ->and(json_decode($prepared->body, true, flags: JSON_THROW_ON_ERROR))->toBe(['body' => 'converted']);
    }
})->with([false, true]);

it('подключает исправление к коллекциям и клиентскому Returns', function (): void {
    $hydrator = relativeHydrator(true, ScalarPolicy::Strict);
    $items = $hydrator->hydrateCollection([['child' => []], ['child' => ['id' => 8]]], SelfNode::class);
    expect($items[0]->child::class)->toBe(SelfNode::class)->and($items[1]->child->id)->toBe(8);
    $transport = new MockTransport();
    $transport->preventStrayRequests();
    $transport->fake([NodeRequest::class => MockResponse::success(['child' => ['id' => 9]])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://relative.test'), $transport);
    $dto = $client->send(new NodeRequest())->dataOrFail();
    expect($dto)->toBeInstanceOf(SelfNode::class)->and($dto->child::class)->toBe(SelfNode::class)
        ->and($dto->child->id)->toBe(9);
});
