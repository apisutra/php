<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Continuation\FinalPathStateResolver;
use ApiSutra\Continuation\ContinuationState;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;
use ApiSutra\Exceptions\Continuation\ContinuationAwaitException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Input\JsonDecoder;
use ApiSutra\Serialization\Rules\DefaultSpec;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HandlerSpec;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\InputShape;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Continuation\RecordingStateResolver;
use ApiSutra\Tests\Stubs\Continuation\WithoutCriterionRequest;
use ApiSutra\Tests\Stubs\HydrationRules\AwaitRequest;
use ApiSutra\Tests\Stubs\HydrationRules\ReturnCast;
use ApiSutra\Tests\Stubs\HydrationRules\ValueDto;
use ApiSutra\Tests\Stubs\JsonContainerShapes\Envelope;
use ApiSutra\Tests\Stubs\JsonContainerShapes\Node;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\HydrationRulesFixture as Fixture;
use ApiSutra\Transport\MockTransport;

function jsonRuleHydrator(FieldRule $rule): Hydrator
{
    return Hydrator::forRules(HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()->field('value', $rule)));
}

it('сохраняет прежнюю неоднозначность готового PHP массива без выдуманного JSON происхождения', function (): void {
    $hydrator = Hydrator::default();
    expect($hydrator->hydrate(['items' => [], 'payload' => []], Envelope::class)->payload)->toBeInstanceOf(Node::class);
    expect($hydrator->hydrate([], Node::class))->toBeInstanceOf(Node::class)
        ->and($hydrator->hydrate(new stdClass(), Node::class))->toBeInstanceOf(Node::class);
});

it('различает missing null object list до defaults и constructor checks', function (): void {
    $rule = FieldRule::create()->required()->forbidExplicitNull()->shape(ValueShape::dto(Node::class, emptyListAsObject: true));
    $hydrator = jsonRuleHydrator($rule);
    foreach (['{}' => 'required_field_missing', '{"value":null}' => 'explicit_null_not_allowed', '{"value":[1]}' => 'invalid_object_shape'] as $json => $reason) {
        expect(Fixture::error(fn () => $hydrator->hydrateInput((new JsonDecoder())->decode($json), ValueDto::class))->reason)->toBe($reason);
    }
    foreach (['{"value":{}}', '{"value":[]}'] as $json) {
        expect($hydrator->hydrateInput((new JsonDecoder())->decode($json), ValueDto::class)->value)->toBeInstanceOf(Node::class);
    }
    $strictInput = jsonRuleHydrator($rule->inputShape(InputShape::Object));
    expect(Fixture::error(fn () => $strictInput->hydrateInput((new JsonDecoder())->decode('{"value":[]}'), ValueDto::class))->reason)->toBe('invalid_object_shape');
    $default = jsonRuleHydrator(FieldRule::create()->shape(ValueShape::dto(Node::class))->default(DefaultSpec::value([], ValueState::Null)));
    expect($default->hydrateInput((new JsonDecoder())->decode('{"value":null}'), ValueDto::class)->value)->toBeInstanceOf(Node::class);
});

it('проверяет результаты cast без устаревших форм исходного узла', function (): void {
    $hydrator = jsonRuleHydrator(FieldRule::create()->cast(new HandlerSpec(ReturnCast::class, [[]]), ValueShape::list(ValueShape::int())));
    expect($hydrator->hydrateInput((new JsonDecoder())->decode('{"value":{}}'), ValueDto::class)->value)->toBe([]);
    $hydrator = jsonRuleHydrator(FieldRule::create()->cast(new HandlerSpec(ReturnCast::class, [[]]), ValueShape::dto(Node::class, emptyListAsObject: true)));
    expect(Fixture::error(fn () => $hydrator->hydrateInput((new JsonDecoder())->decode('{"value":[]}'), ValueDto::class))->reason)->toBe('invalid_field_type');
});

it('сохраняет формы recursive list и разрешение только у указанного элемента DTO', function (): void {
    $hydrator = jsonRuleHydrator(FieldRule::create()->shape(ValueShape::list(ValueShape::list(ValueShape::dto(Node::class, emptyListAsObject: true)))));
    $good = (new JsonDecoder())->decode('{"value":[[[],{}]]}');
    expect($hydrator->hydrateInput($good, ValueDto::class)->value[0])->toHaveCount(2);
    foreach (['{"value":[{}]}', '{"value":[[{"child":[]}]]}'] as $json) {
        expect(Fixture::error(fn () => $hydrator->hydrateInput((new JsonDecoder())->decode($json), ValueDto::class))->reason)
            ->toBeIn(['invalid_list_shape', 'invalid_object_shape']);
    }
});

it('варианты проверяют выбранный payload и маскируют ключи JSON object', function (): void {
    $shape = ValueShape::list(ValueShape::variants('', ['node' => Node::class], NestedDiscriminatorMode::Key), normalizeKeys: true);
    $hydrator = jsonRuleHydrator(FieldRule::create()->shape($shape));
    $input = (new JsonDecoder())->decode('{"value":{"0":{"node":[]}}}');
    $error = Fixture::error(fn () => $hydrator->hydrateInput($input, ValueDto::class));
    expect($error->reason)->toBe('invalid_object_shape')->and($error->path)->toBe('value[0]')
        ->and($error->sourcePath)->toBe('/value/0/node')->and($error->logContext()['sourcePath'])->toBe('/value/*/node');
});

it('не меняет ключи карт и значения extras при сохранении формы', function (): void {
    $json = '{"value":{"01":"a","0":"b","":"c","/~":"d"},"huge":999999999999999999999,"false":false,"zero":0,"extra":{}}';
    $hydrator = Hydrator::forRules(HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()->extras('extra')));
    $dto = $hydrator->hydrateInput((new JsonDecoder())->decode($json), ValueDto::class);
    expect($dto->value)->toBe(['01' => 'a', 0 => 'b', '' => 'c', '/~' => 'd'])
        ->and($dto->extra)->toBe(['huge' => '999999999999999999999', 'false' => false, 'zero' => 0, 'extra' => []]);
});

it('сохраняет форму в continuation и повторной гидратации outcome без карты соседних полей', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('{"data":{"child":{}},"unrelated":[{},{}]}')]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://shape.test'), $transport);
    $request = new AwaitRequest();
    $start = $client->send($request)->raw();
    $outcome = $client->continuation()->resolveFromStartResult($start, $request, Node::class);
    expect($outcome->value->child)->toBeInstanceOf(Node::class)
        ->and(array_keys($outcome->input()->shape->children))->toBe(['child']);
    $copy = $client->continuation()->hydrateOutcome($outcome, Node::class);
    expect($copy->input()->shape)->toBe($outcome->input()->shape)
        ->and($copy->value->child)->toBeInstanceOf(Node::class);
    $transport->fake(['*' => MockResponse::make('{"data":{"child":[]}}')]);
    try {
        $client->send(new AwaitRequest())->awaitAs(Node::class);
        throw new LogicException('Ожидалась ошибка формы continuation');
    } catch (ContinuationAwaitException $exception) {
        expect($exception->getPrevious()->reason)->toBe('invalid_object_shape')
            ->and($exception->getPrevious()->sourcePath)->toBe('/data/child');
    }
});

it('путь custom resolver не приписывает JSON форму его новым данным', function (): void {
    $resolver = new RecordingStateResolver();
    $resolver->state = ContinuationState::ready([], 'data');
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('{"data":[1]}')]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://shape.test', continuationStateResolver: $resolver), $transport);
    expect($client->send(new WithoutCriterionRequest())->awaitAs(Node::class))->toBeInstanceOf(Node::class);
});

it('continuation использует режим клиента даже с явно переданным штатным resolver', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('{"data":{"child":[]}}')]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://shape.test',
        hydration: new HydrationConfig(jsonShapeValidation: false),
        continuationStateResolver: new FinalPathStateResolver(),
    ), $transport);
    $request = new AwaitRequest();
    $start = $client->send($request)->raw();
    $outcome = $client->continuation()->resolveFromStartResult($start, $request, Node::class);
    expect($outcome->input()->jsonSourceKnown)->toBeFalse()->and($outcome->input()->shape)->toBeNull()
        ->and($outcome->value->child)->toBeInstanceOf(Node::class)
        ->and($client->continuation()->hydrateOutcome($outcome, Node::class)->value->child)->toBeInstanceOf(Node::class);
    $transport->fake(['*' => MockResponse::make('{"data":["lost"]}')]);
    expect(fn () => $client->send(new AwaitRequest())->awaitAs(Node::class))->toThrow(ContinuationAwaitException::class);
});

it('внешние правила сохраняют guard и маскируют неизвестные числовые ключи при выключении', function (): void {
    $shape = ValueShape::list(ValueShape::variants('', ['node' => Node::class], NestedDiscriminatorMode::Key), normalizeKeys: true);
    $rules = HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->shape($shape)));
    $hydrator = Hydrator::forConfig(new HydrationConfig(rules: $rules, jsonShapeValidation: false));
    $input = (new JsonDecoder())->decode('{"value":{"0":{"node":["lost"]}}}', false);
    $error = Fixture::error(fn () => $hydrator->hydrateInput($input, ValueDto::class));
    expect($error->reason)->toBe('invalid_object_shape')->and($error->sourcePath)->toBe('/value/0/node')
        ->and($error->logContext()['sourcePath'])->toBe('/value/*/node');
});
