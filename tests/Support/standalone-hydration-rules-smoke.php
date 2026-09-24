<?php

declare(strict_types=1);

use ApiSutra\Config\HydrationConfig;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\DtoRules;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HandlerSpec;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Requests\HydrationProbeRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use Example\HydrationRules\EntryCast;
use Example\HydrationRules\EntryDto;
use Example\HydrationRules\ReportDto;

$checkout = $argv[1] ?? dirname(__DIR__, 2);
require $checkout . '/vendor/autoload.php';
require_once __DIR__ . '/../Stubs/TestClient.php';
require_once __DIR__ . '/../Stubs/Requests/HydrationProbeRequest.php';
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'Illuminate\\')) {
        throw new RuntimeException('Правила загрузили Illuminate: ' . $class);
    }
}, prepend: true);

// Исполняем именно опубликованные модели, builders и scoped cast.
ob_start();
require $checkout . '/docs/example/hydration-rules/run.php';
ob_end_clean();
$observations = [
    'graph' => [$dto->owner->id, $dto->owner->_extra, $dto->items[0]->id, $dto->ids, $dto->count],
    'extras' => $dto->_extra,
];
// Совпадающие имена в API сохраняются внутри явно настроенного receiver.
$collision = Hydrator::forRules($rules)->hydrate([
    'record_id' => 7, 'extra' => ['enabled' => true], '_extra' => 'remote',
], EntryDto::class);
$observations['receiver_collision'] = [$collision->id, $collision->_extra];
$source['rows'][] = ['value' => ['record_id' => '9']];
try {
    Hydrator::forRules($rules)->hydrate($source, ReportDto::class);
    throw new RuntimeException('Strict не отклонил числовую строку');
} catch (HydrationException $error) {
    $observations['strict'] = [$error->reason, $error->path, $error->sourcePath];
}
$source['rows'][1]['value']['record_id'] = 9;
$transport = new MockTransport();
$transport->preventStrayRequests();
$transport->fake(['*' => MockResponse::success($source)]);
$client = new TestClient($config, $transport);
$observations['http'] = $client->send(new HydrationProbeRequest(ReportDto::class))->dataOrFail()->items[1]->id;
$observations['wire'] = (new DtoSerializer(new CastRegistry(), config: new HydrationConfig(rules: $rules)))->serialize($dto)['owner'];
$scoped = HydrationRules::create(new RulePolicy(scalars: ScalarPolicy::Strict))
    ->withDto(EntryDto::class, $rules->rulesFor(EntryDto::class))
    ->withDto(ReportDto::class, DtoRules::create()->field('owner', FieldRule::create()->cast(new HandlerSpec(EntryCast::class))));
$observations['scope'] = Hydrator::forRules($scoped)->hydrate([
    'owner' => ['record_id' => 11], 'items' => [], 'ids' => [],
], ReportDto::class)->owner->id;
$observations['illuminate'] = array_values(array_filter(
    get_declared_classes(),
    static fn (string $class): bool => str_starts_with($class, 'Illuminate\\'),
));
$expected = [
    'graph' => [7, ['future' => false], 8, [1, 2], null],
    'extras' => ['rows' => [['sourceKey' => 0, 'remainder' => ['meta' => ['revision' => 2]]]], 'next_feature' => null],
    'receiver_collision' => [7, ['extra' => ['enabled' => true], '_extra' => 'remote']],
    'strict' => ['invalid_field_type', 'items[1].id', '/rows/1/value/record_id'],
    'http' => 9,
    'wire' => ['id' => 7],
    'scope' => 11,
    'illuminate' => [],
];
if ($observations !== $expected) {
    throw new RuntimeException('Нарушен опубликованный контракт: ' . json_encode($observations));
}
echo json_encode($observations, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . PHP_EOL;
