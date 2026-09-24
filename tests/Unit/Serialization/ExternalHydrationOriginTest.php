<?php

declare(strict_types=1);

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Casts\JsonCast;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Enums\DataTransfer\NestedUnknownVariant;
use ApiSutra\Tests\Stubs\HydrationRules\ComputedRecordDto;
use ApiSutra\Tests\Stubs\HydrationRules\ArrayReceiverDto;
use ApiSutra\Serialization\Rules\DefaultSpec;
use ApiSutra\Serialization\Rules\HandlerSpec;
use ApiSutra\Tests\Stubs\HydrationRules\FailingProvider;
use ApiSutra\Tests\Stubs\HydrationRules\ReportRequest;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Serialization\Rules\ValueShape;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\HydrationRules\HookedReportRequest;
use ApiSutra\Tests\Stubs\HydrationRules\NodeDto;
use ApiSutra\Tests\Stubs\HydrationRules\PairDto;
use ApiSutra\Tests\Stubs\HydrationRules\RecordDto;
use ApiSutra\Tests\Stubs\HydrationRules\ValueDto;
use ApiSutra\Tests\Stubs\Requests\HydrationProbeRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Support\HydrationRulesFixture;
use ApiSutra\Transport\MockTransport;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

it('различает найденный fallback, null primary и Missing с кандидатами', function (): void {
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(RecordDto::class, DtoRules::create()->field('id', FieldRule::create()->from('primary', 'fallback')));
    $hydrator = Hydrator::forRules($rules);
    foreach ([['fallback' => 'bad'], ['primary' => null, 'fallback' => 7], []] as $source) {
        $error = HydrationRulesFixture::error(fn () => $hydrator->hydrate($source, RecordDto::class));
        expect($error->sourcePath)->toBe(array_key_exists('primary', $source) || $source === [] ? '/primary' : '/fallback')
            ->and($error->sourcePathKind)->toBe($source === [] ? SourcePathKind::Expected : SourcePathKind::Resolved);
        if ($source === []) {
            expect($error->sourceCandidates)->toBe(['/primary', '/fallback']);
        }
    }
    $error = HydrationRulesFixture::error(fn () => Hydrator::default()->hydrate([], RecordDto::class));
    expect($error->sourcePathKind)->toBeNull()->and($error->context())->not->toHaveKey('sourcePath');
});

it('экранирует и маскирует строковые и числовые ключи нормализованного словаря', function (int|string $key): void {
    $logger = new class extends AbstractLogger {
        /** @var list<array<string, mixed>> */
        public array $entries = [];
        public function log(mixed $level, Stringable|string $message, array $context = []): void
        {
            $this->entries[] = ['message' => (string) $message, 'context' => $context];
        }
    };
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->shape(ValueShape::list(ValueShape::dto(RecordDto::class), normalizeKeys: true))));
    $transport = new MockTransport();
    $transport->fake([HydrationProbeRequest::class => MockResponse::success(['value' => [$key => ['id' => 'invalid']]])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://rules.test', hydration: new HydrationConfig(rules: $rules), logger: $logger, logLevel: LogLevel::ERROR, debug: false), $transport);
    $result = $client->send(new HydrationProbeRequest(ValueDto::class))->raw();
    $error = $result->exception;
    $pointerKey = str_replace(['~', '/'], ['~0', '~1'], (string) $key);
    expect($error->path)->toBe('value[0].id')->and($error->sourcePath)->toBe('/value/' . $pointerKey . '/id')
        ->and($error->logContext()['sourcePath'])->toBe('/value/*/id')
        ->and($result->errors->first()->context['sourcePath'])->toBe($error->sourcePath)
        ->and(json_encode($logger->entries))->not->toContain($pointerKey)
        ->and($logger->entries[0]['context']['sourcePath'])->toBe('/value/*/id');
})->with(['secret.[key]/~x', 123456789]);

it('отмечает BeforeHydrate как границу исходного HTTP документа', function (): void {
    $payload = HydrationRulesFixture::payload();
    $payload['record_id'] = 'invalid';
    $transport = new MockTransport();
    $transport->fake([HookedReportRequest::class => MockResponse::success(['envelope' => $payload])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://rules.test', hydration: new HydrationConfig(rules: HydrationRulesFixture::rules())), $transport);
    $error = $client->send(new HookedReportRequest())->raw()->exception;
    expect($error->path)->toBe('data.id')->and($error->sourcePathKind)->toBe(SourcePathKind::Boundary)
        ->and($error->sourcePath)->toBe('');
});

it('ограничивает активные DTO узлы и разрешает общий объект в независимых ветках', function (): void {
    $rules = HydrationRules::create()->withDto(NodeDto::class, DtoRules::create()
        ->field('child', FieldRule::create()->shape(ValueShape::nullable(ValueShape::dto(NodeDto::class)))))
        ->withDto(PairDto::class, DtoRules::create()
            ->field('left', FieldRule::create()->shape(ValueShape::dto(NodeDto::class)))
            ->field('right', FieldRule::create()->shape(ValueShape::dto(NodeDto::class))));
    $hydrator = Hydrator::forRules($rules);
    $source = [];
    for ($i = 1; $i < 512; $i++) {
        $source = ['child' => $source];
    }
    $node = $hydrator->hydrate($source, NodeDto::class);
    $count = 0;
    do {
        $count++;
        $node = $node->child;
    } while ($node !== null);
    expect($count)->toBe(512);
    $error = HydrationRulesFixture::error(fn () => $hydrator->hydrate(['child' => $source], NodeDto::class));
    expect($error->reason)->toBe('hydration_depth_exceeded')
        ->and($error->path)->toBe(implode('.', array_fill(0, 512, 'child')))
        ->and($error->sourcePath)->toBe(str_repeat('/child', 512))
        ->and($error->getPrevious())->toBeInstanceOf(HydrationException::class)
        ->and($error->getPrevious()->getPrevious())->toBeNull();
    $cycle = new stdClass();
    $cycle->child = $cycle;
    expect(HydrationRulesFixture::error(fn () => $hydrator->hydrate($cycle, NodeDto::class))->reason)->toBe('cyclic_hydration_input');
    $shared = new stdClass();
    $pair = $hydrator->hydrate(['left' => $shared, 'right' => $shared], PairDto::class);
    expect($pair->left)->toBeInstanceOf(NodeDto::class)->and($pair->right)->toBeInstanceOf(NodeDto::class)
        ->and($pair->left)->not->toBe($pair->right);
});

it('указывает Boundary для JsonCast и provider Missing/Present', function (): void {
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()
            ->cast(new HandlerSpec(JsonCast::class), ValueShape::list(ValueShape::int()))));
    $error = HydrationRulesFixture::error(fn () => Hydrator::forRules($rules)->hydrate(['value' => '[1,"2"]'], ValueDto::class));
    expect($error->path)->toBe('value[1]')->and($error->sourcePath)->toBe('/value')
        ->and($error->sourcePathKind)->toBe(SourcePathKind::Boundary);
    $rules = HydrationRules::create()->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()
        ->default(DefaultSpec::provider(new HandlerSpec(FailingProvider::class), ValueState::Missing, ValueState::Present))));
    foreach ([[], ['value' => 7]] as $input) {
        $error = HydrationRulesFixture::error(fn () => Hydrator::forRules($rules)->hydrate($input, ValueDto::class));
        expect($error->path)->toBe('value')->and($error->sourcePath)->toBe('/value')
            ->and($error->sourcePathKind)->toBe(SourcePathKind::Boundary);
    }
});

it('дополняет ошибки unwrap и формы до входа в гидратор', function (array $payload, SourcePathKind $kind): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success($payload)]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://rules.test', hydration: new HydrationConfig(rules: HydrationRulesFixture::rules())), $transport);
    $error = $client->send(new ReportRequest())->raw()->exception;
    expect($error->sourcePath)->toBe('/data')->and($error->sourcePathKind)->toBe($kind);
})->with([[[], SourcePathKind::Expected], [['data' => 7], SourcePathKind::Resolved]]);

it('сохраняет source после Skip и маскирует исходный ключ словаря', function (): void {
    $rules = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
        ->withDto(ValueDto::class, DtoRules::create()->field('value', FieldRule::create()->shape(ValueShape::list(
            ValueShape::variants('type', ['known' => RecordDto::class], unknown: NestedUnknownVariant::Skip), normalizeKeys: true,
        ))));
    $error = HydrationRulesFixture::error(fn () => Hydrator::forRules($rules)->hydrate([
        'value' => ['skipped' => ['type' => 'unknown'], 'secret' => ['type' => 'known', 'id' => 'bad']],
    ], ValueDto::class));
    expect($error->path)->toBe('value[1].id')->and($error->sourcePath)->toBe('/value/secret/id')
        ->and($error->logContext()['sourcePath'])->toBe('/value/*/id');
});

it('не выдаёт synthetic computed и toArray за исходный путь', function (): void {
    $hydrator = Hydrator::forRules(HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict)));
    $computed = HydrationRulesFixture::error(fn () => $hydrator->hydrate(['source' => 'bad'], ComputedRecordDto::class));
    $converted = HydrationRulesFixture::error(fn () => $hydrator->hydrate(new ArrayReceiverDto(['id' => 'bad']), RecordDto::class));
    foreach ([$computed, $converted] as $error) {
        expect($error->sourcePathKind)->toBe(SourcePathKind::Boundary)->and($error->sourcePath)->toBe('');
    }
});
