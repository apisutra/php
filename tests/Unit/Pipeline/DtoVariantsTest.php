<?php

declare(strict_types=1);

use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Continuation\ContinuationAwaitException;
use ApiSutra\Exceptions\Serialization\ResponseTypeMismatchException;
use ApiSutra\Execution\Async\AsyncTask;
use ApiSutra\Pipeline\Hydration\ResponseContractGuard;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\HydrationRules\AwaitRequest;
use ApiSutra\Tests\Stubs\PolymorphicInput\AmbiguousEvent;
use ApiSutra\Tests\Stubs\PolymorphicInput\Event;
use ApiSutra\Tests\Stubs\PolymorphicInput\EventEnvelope;
use ApiSutra\Tests\Stubs\PolymorphicInput\EventPageRequest;
use ApiSutra\Tests\Stubs\PolymorphicInput\EventRequest;
use ApiSutra\Tests\Stubs\PolymorphicInput\ExternalCollection;
use ApiSutra\Tests\Stubs\PolymorphicInput\ExternalEvent;
use ApiSutra\Tests\Stubs\PolymorphicInput\ExternalOne;
use ApiSutra\Tests\Stubs\PolymorphicInput\ExternalTwo;
use ApiSutra\Tests\Stubs\PolymorphicInput\MessageEvent;
use ApiSutra\Tests\Stubs\PolymorphicInput\RawEvent;
use ApiSutra\Tests\Stubs\PolymorphicInput\UnwrapEventRequest;
use ApiSutra\Tests\Stubs\Requests\HydrationProbeRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Tracing\ObserverProvider;
use ApiSutra\Tests\Stubs\Tracing\RecordingObserver;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Pipeline\PipelineContext;
use Revolt\EventLoop;

it('HTTP sync async и webhook дают одинаковый тип с одной трассой на исполнение', function (bool $async, bool $unwrap): void {
    $json = '{"type":"message","text":"secret-payload","permissions":[]}';
    $observer = new RecordingObserver();
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make($unwrap ? '{"data":' . $json . '}' : $json)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://variants.test', containerProvider: new ObserverProvider($observer)), $transport);
    $request = $unwrap ? new UnwrapEventRequest() : new EventRequest();
    $dto = ($async ? $client->sendAsync($request)->wait() : $client->send($request))->dataOrFail();
    expect($dto)->toEqual(Hydrator::default()->hydrateJson($json, Event::class))
        ->and($observer->active)->toBe(0)->and($observer->snapshots)->toHaveCount(1)
        ->and(json_encode($observer->snapshots))->not->toContain('secret-payload');
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe([]);
})->with([false, true])->with([false, true]);

it('типовые варианты работают в native nullable Nested DtoShape и списке при разных кешах метаданных', function (bool $cacheOn): void {
    $cache = new AttributeMetadataCache($cacheOn);
    $cache->warmup([Event::class]);
    $hydrator = new Hydrator(new CastRegistry(), $cache);
    $dto = $hydrator->hydrateJson('{"native":{"type":"message"},"nested":{"type":"message"},"shaped":{"type":"future","payload":1},"items":[{"type":"message"},{"type":"future"}]}', EventEnvelope::class);
    expect($dto->native)->toBeInstanceOf(MessageEvent::class)->and($dto->nested)->toBeInstanceOf(MessageEvent::class)
        ->and($dto->shaped)->toBeInstanceOf(RawEvent::class)->and($dto->items[0])->toBeInstanceOf(MessageEvent::class)
        ->and($dto->items[1])->toBeInstanceOf(RawEvent::class);
    expect(fn () => $hydrator->hydrate(['value' => ['type' => 'message']], AmbiguousEvent::class))->toThrow(ConfigurationException::class);
})->with([false, true]);

it('проверяет всю карту до HTTP и не кеширует вердикт по классу динамического запроса', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('{"type":"x"}')]);
    $rules = HydrationRules::create()->withVariants(ExternalEvent::class, 'type', ['x' => ExternalOne::class, 'bad' => 'Missing\\Dto']);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://variants.test', hydration: new HydrationConfig(rules: $rules)), $transport);
    $result = $client->send(new HydrationProbeRequest(ExternalEvent::class))->raw();
    expect($result->errors->first()->code->value)->toBe('configuration_error')->and($transport->getRecorded())->toBe([]);
    expect($client->send(new HydrationProbeRequest(ExternalOne::class))->raw()->isSuccess())->toBeTrue();
    expect($client->send(new HydrationProbeRequest(ExternalEvent::class))->raw()->isFailed())->toBeTrue();
    expect($transport->getRecorded())->toHaveCount(1);
});

it('собирает типизированные items страниц с вариантами и fallback', function (int $concurrency): void {
    $observer = new RecordingObserver();
    $transport = new MockTransport();
    $transport->fake(['*' => static function ($request): MockResponse {
        $page = $request->getContext()->paginationOptions->getPage();
        return MockResponse::success(['data' => [['type' => $page === 1 ? 'message' : 'future', 'text' => (string) $page]], 'meta' => ['page' => $page, 'per_page' => 1, 'total' => 3]]);
    }]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://variants.test', hydration: new HydrationConfig(), containerProvider: new ObserverProvider($observer)), $transport);
    $result = new EventPageRequest()->setClient($client)->paginate()->withConcurrency($concurrency)->all();
    expect($result->isSuccess())->toBeTrue()->and($result->items())->toHaveCount(3)
        ->and($result->items()[0])->toBeInstanceOf(MessageEvent::class)->and($result->items()[1])->toBeInstanceOf(RawEvent::class)
        ->and($result->items()[2]->raw['text'])->toBe('3')->and($observer->active)->toBe(0)->and($observer->snapshots)->toHaveCount(4);
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe([]);
})->with([1, 3]);

it('повторно гидратирует continuation через тот же тип не теряя форму исходного узла', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('{"data":{"type":"message","permissions":[]}}')]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://variants.test'), $transport);
    $request = new AwaitRequest();
    $start = $client->send($request)->raw();
    $outcome = $client->continuation()->resolveFromStartResult($start, $request, Event::class);
    expect($outcome->value)->toBeInstanceOf(MessageEvent::class)
        ->and($client->continuation()->hydrateOutcome($outcome, Event::class)->value)->toEqual($outcome->value);
    expect(new AwaitRequest()->setClient($client)->send()->awaitAs(Event::class))->toBeInstanceOf(MessageEvent::class);
    $transport->fake(['*' => MockResponse::make('{"data":{"type":"message","permissions":{}}}')]);
    expect(fn () => new AwaitRequest()->setClient($client)->send()->awaitAs(Event::class))->toThrow(ContinuationAwaitException::class);
});

it('hooks используют объявленный тип до выбора и runtime тип после без двойного вызова', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make('{"type":"message","permissions":[]}')]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://variants.test'), $transport);
    $hooks = [];
    foreach ([Event::class, MessageEvent::class] as $type) {
        foreach ([Hook::BeforeHydrate, Hook::AfterHydrate] as $stage) {
            $hook = new class implements HookInterface {
                public int $calls = 0;
                public function handle(PipelineContext $context): ?array
                {
                    $this->calls++;
                    return null;
                }
            };
            $client->hooks()->on($stage, $hook, forDto: $type);
            $hooks[$type][$stage->value] = $hook;
        }
    }
    expect($client->send(new EventRequest())->raw()->isSuccess())->toBeTrue()
        ->and($hooks[Event::class][Hook::BeforeHydrate->value]->calls)->toBe(1)
        ->and($hooks[MessageEvent::class][Hook::BeforeHydrate->value]->calls)->toBe(0)
        ->and($hooks[Event::class][Hook::AfterHydrate->value]->calls)->toBe(0)
        ->and($hooks[MessageEvent::class][Hook::AfterHydrate->value]->calls)->toBe(1);
});

it('финальный guard принимает готовый подтип без гидратации и отклоняет чужой DTO', function (): void {
    $request = new EventRequest();
    $config = new ClientConfig(baseUrl: 'https://variants.test');
    ResponseContractGuard::check($request, $config, new MessageEvent());
    ResponseContractGuard::check($request, $config, new RawEvent());
    expect(fn () => ResponseContractGuard::check($request, $config, new ExternalOne()))->toThrow(ResponseTypeMismatchException::class);
});

it('чередующиеся async клиенты не делят внешнюю карту одного интерфейса', function (): void {
    $clients = [];
    foreach ([ExternalOne::class, ExternalTwo::class] as $class) {
        $transport = new MockTransport();
        $transport->fake(['*' => static function (): MockResponse {
            AsyncTask::current()->runtime->sleep(1);
            return MockResponse::make('{"type":"x"}');
        }]);
        $rules = HydrationRules::create()->withVariants(ExternalEvent::class, 'type', ['x' => $class]);
        $clients[] = new TestClient(new ClientConfig(baseUrl: 'https://variants.test', hydration: new HydrationConfig(rules: $rules)), $transport);
    }
    $one = $clients[0]->sendAsync(new HydrationProbeRequest(ExternalEvent::class));
    $two = $clients[1]->sendAsync(new HydrationProbeRequest(ExternalEvent::class));
    expect($two->wait()->dataOrFail())->toBeInstanceOf(ExternalTwo::class)
        ->and($one->wait()->dataOrFail())->toBeInstanceOf(ExternalOne::class);
    EventLoop::run();
    expect(EventLoop::getIdentifiers())->toBe([]);
});

it('membership коллекции не требует декларации а сериализация не выдумывает discriminator', function (): void {
    $one = new ExternalOne();
    expect(new ExternalCollection([$one])->all())->toBe([$one]);
    $dto = Hydrator::default()->hydrateJson('{"type":"message","text":"hello"}', Event::class);
    $array = DtoSerializer::default()->serialize($dto);
    expect($array)->not->toHaveKey('type')->and($array['text'])->toBe('hello');
});
