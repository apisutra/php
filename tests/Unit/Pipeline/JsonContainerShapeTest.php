<?php

declare(strict_types=1);

use ApiSutra\Attributes\Response\Returns;
use ApiSutra\Config\CacheConfig;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\DtoMapping\CallbackHydrator;
use ApiSutra\Tests\Stubs\HydrationRules\ContainerRequest;
use ApiSutra\Tests\Stubs\HydrationRules\ItemsRequest;
use ApiSutra\Tests\Stubs\JsonContainerShapes\ComputedDto;
use ApiSutra\Tests\Stubs\JsonContainerShapes\Envelope;
use ApiSutra\Tests\Stubs\JsonContainerShapes\Node;
use ApiSutra\Tests\Stubs\JsonContainerShapes\ResponseDto;
use ApiSutra\Tests\Stubs\JsonContainerShapes\ShapeRequest;
use ApiSutra\Tests\Stubs\JsonContainerShapes\InterleavedDto;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\ArrayCache;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Pipeline\PipelineContext;
use Revolt\EventLoop;
use GuzzleHttp\Promise\Utils;

function jsonShapeResponse(string $json, ?Returns $returns = null, bool $async = false, ?HydrationConfig $config = null): ExecutionResult
{
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make($json)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://shape.test', hydration: $config), $transport);
    $request = new ShapeRequest($returns ?? new Returns(Envelope::class));
    return ($async ? $client->sendAsync($request)->wait() : $client->send($request))->raw();
}

it('проверяет исходную JSON форму во всех штатных входах', function (string $json, ?string $reason, ?string $path): void {
    foreach ([false, true] as $async) {
        $result = jsonShapeResponse($json, async: $async);
        expect($result->isSuccess())->toBe($reason === null);
        if ($reason !== null) {
            expect($result->errors->first()->code)->toBe(ErrorCode::HydrationError)
                ->and($result->exception)->toBeInstanceOf(HydrationException::class)
                ->and($result->exception->reason)->toBe($reason)
                ->and($result->exception->path)->toBe($path);
        }
    }
    expect(EventLoop::getIdentifiers())->toBe([]);
})->with([
    ['{"items":[]}', null, null],
    ['{"items":["a","b"]}', null, null],
    ['{"items":{}}', 'invalid_list_shape', 'items'],
    ['{"items":{"0":"a","1":"b"}}', 'invalid_list_shape', 'items'],
    ['{"\\u0000key":{},"items":{"0":"a","1":"b"}}', 'invalid_list_shape', 'items'],
    ['{"payload":{}}', null, null],
    ['{"payload":[]}', 'invalid_object_shape', 'payload'],
    ['{"payload":["a"]}', 'invalid_object_shape', 'payload'],
    ['{"payload":{"child":[]}}', 'invalid_object_shape', 'payload.child'],
    ['{"nested":[]}', 'unexpected_response_shape', 'nested'],
    ['{"native":[]}', 'invalid_object_shape', 'native'],
    ['{"options":[]}', null, null],
    ['{"options":[null]}', 'invalid_object_shape', 'options'],
    ['{"options":{"child":[]}}', 'invalid_object_shape', 'options.child'],
    ['{"required":[]}', 'required_field_missing', 'required.id'],
    ['{"normalized":{}}', null, null],
    ['{"normalized":{"0":"a","1":"b"}}', null, null],
    ['{"response":{"rows":[{"value":[]}]}}', 'invalid_object_shape', 'rows[0]'],
]);

it('строго проверяет корень и выбранный Returns DTO с локальным разрешением пустого списка', function (): void {
    foreach (['[]', '["a"]', '[null]'] as $json) {
        $error = jsonShapeResponse($json, new Returns(Node::class))->exception;
        expect($error->reason)->toBe('invalid_object_shape')->and($error->path)->toBe('$')
            ->and($error->sourcePath)->toBe('')->and($error->actual)->toBe('list');
    }
    expect(jsonShapeResponse('{}', new Returns(Node::class))->isSuccess())->toBeTrue();
    expect(jsonShapeResponse('[]', new Returns(Node::class, emptyListAsObject: true))->isSuccess())->toBeTrue();
    expect(jsonShapeResponse('["a"]', new Returns(Node::class, emptyListAsObject: true))->isFailed())->toBeTrue();
    $returns = new Returns(Envelope::class, unwrap: 'data', type: Node::class, emptyListAsObject: true);
    expect(jsonShapeResponse('{"data":[]}', $returns)->data)->toBeInstanceOf(Node::class);
    $error = jsonShapeResponse('{"data":{"child":[]}}', $returns)->exception;
    expect($error->path)->toBe('data.child')->and($error->sourcePath)->toBe('/data/child');
});

it('проверяет форму до custom гидратора и передаёт ему обычные значения', function (): void {
    $calls = [];
    $custom = new CallbackHydrator(function ($data) use (&$calls): object {
        $calls[] = $data;
        return new Node();
    });
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('[]')]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://shape.test', hydration: new HydrationConfig(hydrator: $custom)), $transport);
    expect($client->send(new ShapeRequest(new Returns(Node::class)))->raw()->isFailed())->toBeTrue()
        ->and($calls)->toBe([]);
    expect($client->send(new ShapeRequest(new Returns(Node::class, emptyListAsObject: true)))->dataOrFail())->toBeInstanceOf(Node::class)
        ->and($calls)->toBe([[]]);
});

it('различает identity computed и пользовательскую замену', function (): void {
    expect(jsonShapeResponse('{"items":{}}', new Returns(ResponseDto::class))->exception->reason)->toBe('invalid_list_shape');
    expect(jsonShapeResponse('{"items":{}}', new Returns(ComputedDto::class))->data->items)->toBe([]);
});

it('наблюдатель BeforeHydrate сохраняет форму, массив от hook создаёт новый PHP вход', function (bool $replace): void {
    $hook = new class ($replace) implements HookInterface {
        public function __construct(private bool $replace)
        {
        }
        public function handle(PipelineContext $context): ?array
        {
            return $this->replace ? ['items' => []] : null;
        }
    };
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('{"items":{}}')]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://shape.test'), $transport);
    $client->hooks()->on(Hook::BeforeHydrate, $hook);
    expect($client->send(new ShapeRequest())->raw()->isSuccess())->toBe($replace);
})->with([false, true]);

it('повторяет проверку на cache hit из исходного JSON', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('{"items":{}}')]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://shape.test', cacheConfig: new CacheConfig(store: new ArrayCache())), $transport);
    expect(new ShapeRequest(new Returns(ComputedDto::class))->setClient($client)->withCache()->send()->raw()->isSuccess())->toBeTrue();
    for ($i = 0; $i < 2; $i++) {
        $result = new ShapeRequest()->setClient($client)->withCache()->send()->raw();
        expect($result->exception->reason)->toBe('invalid_list_shape');
    }
    expect($transport->getRecorded())->toHaveCount(1);
});

it('пагинация сохраняет map коллекции, но проверяет DTO элементов', function (string $request): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('{"response":{"rows":{"0":[]}}}')]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://shape.test', hydration: new HydrationConfig()), $transport);
    $error = $client->send(new $request())->raw()->exception;
    expect($error->reason)->toBe('invalid_object_shape')
        ->and($error->path)->toBe('response.rows[0]')->and($error->sourcePath)->toBe('/response/rows/0')
        ->and($error->logContext()['sourcePath'])->toBe('/response/rows/*');
    $transport->fake(['*' => MockResponse::make('{"response":{"rows":{"name":{"id":7}}}}')]);
    expect($client->send(new $request())->raw()->isSuccess())->toBeTrue();
})->with([ItemsRequest::class, ContainerRequest::class]);

it('сохраняет синтетический пустой вход для null и пустого тела', function (string $body): void {
    expect(jsonShapeResponse($body, new Returns(Node::class))->data)->toBeInstanceOf(Node::class);
})->with(['null', '']);

it('изолирует карты при чередовании Fiber и восстанавливает источник после cast', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::sequence([
        MockResponse::make('{"gate":1,"items":{}}'),
        MockResponse::make('{"gate":2,"items":[]}'),
    ])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://shape.test'), $transport);
    $results = Utils::all([
        $client->sendAsync(new ShapeRequest(new Returns(InterleavedDto::class))),
        $client->sendAsync(new ShapeRequest(new Returns(InterleavedDto::class))),
    ])->wait();
    expect($results[0]->raw()->exception->reason)->toBe('invalid_list_shape')
        ->and($results[1]->dataOrFail()->items)->toBe([])
        ->and(EventLoop::getIdentifiers())->toBe([]);
});

it('выключает точную форму на клиенте сохраняя базовые проверки во всём обходе', function (string $json, string $dto, bool $success): void {
    foreach ([false, true] as $async) {
        $result = jsonShapeResponse($json, new Returns($dto), $async, new HydrationConfig(jsonShapeValidation: false));
        expect($result->isSuccess())->toBe($success);
        if (!$success) {
            expect($result->errors->first()->code)->toBe(ErrorCode::HydrationError);
        }
    }
})->with([
    ['{"items":{},"payload":[],"nested":[]}', Envelope::class, true],
    ['{"native":[]}', Envelope::class, false],
    ['{"items":{"0":"a","1":"b"}}', Envelope::class, true],
    ['{"items":{"name":"a"}}', Envelope::class, false],
    ['{"payload":{"child":[]}}', Envelope::class, true],
    ['{"required":[]}', Envelope::class, false],
    ['{"options":[1]}', Envelope::class, false],
    ['{"items":null}', Envelope::class, false],
    ['{"payload":["a"]}', Envelope::class, false],
    ['[]', Node::class, true],
    ['["a"]', Node::class, false],
    ['{"\\u0000key":{},"items":{}}', Envelope::class, true],
]);

it('наследует выключенный режим в типизированной пагинации и не возвращает потерю элементов DTO', function (string $request): void {
    $transport = new MockTransport();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://shape.test', hydration: new HydrationConfig(jsonShapeValidation: false)), $transport);
    $transport->fake(['*' => MockResponse::make('{"response":{"rows":{"0":[]}}}')]);
    expect($client->send(new $request())->raw()->exception->reason)->toBe('required_field_missing');
    $transport->fake(['*' => MockResponse::make('{"response":{"rows":{"0":{"id":7}}}}')]);
    expect($client->send(new $request())->raw()->isSuccess())->toBeTrue();
    $transport->fake(['*' => MockResponse::make('{"response":{"rows":{"0":["lost"]}}}')]);
    $error = $client->send(new $request())->raw()->exception;
    expect($error->reason)->toBe('invalid_object_shape')
        ->and($error->sourcePath)->toBe('/response/rows/0')
        ->and($error->logContext()['sourcePath'])->toBe('/response/rows/*');
})->with([ItemsRequest::class, ContainerRequest::class]);

it('изолирует режимы клиентов при чередовании Fiber', function (): void {
    $pending = [];
    foreach ([true, false] as $mode) {
        $transport = new MockTransport();
        $transport->fake(['*' => MockResponse::make('{"gate":1,"items":{}}')]);
        $client = new TestClient(new ClientConfig(baseUrl: 'https://shape.test', hydration: new HydrationConfig(jsonShapeValidation: $mode)), $transport);
        $pending[] = $client->sendAsync(new ShapeRequest(new Returns(InterleavedDto::class)));
    }
    $results = Utils::all($pending)->wait();
    expect($results[0]->raw()->exception->reason)->toBe('invalid_list_shape')
        ->and($results[1]->dataOrFail()->items)->toBe([])
        ->and(EventLoop::getIdentifiers())->toBe([]);
});
