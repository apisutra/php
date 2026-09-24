<?php

declare(strict_types=1);

// Запуск: php tests/Support/standalone-json-smoke.php /path/to/no-dev-checkout
use ApiSutra\Attributes\AttributeRegistry;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Casts\JsonCast;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\RetryConfig;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Extensions\ExtensionRegistry;
use ApiSutra\Hooks\HookRegistry;
use ApiSutra\Pipeline\Hydration\ResponseHydrator;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Core\PsrNetworkFailure;
use ApiSutra\Tests\Stubs\Core\SequenceHttpClient;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use ApiSutra\Tests\Stubs\Dto\HydrationRequiredNullableDto;
use ApiSutra\Tests\Stubs\Dto\HydrationJsonPayloadDto;
use ApiSutra\Tests\Stubs\Requests\HydrationProbeRequest;
use ApiSutra\Tests\Stubs\Dto\StringIdentifierDto;
use ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use ApiSutra\Tests\Stubs\Requests\JsonPayloadRequest;
use ApiSutra\Tests\Stubs\Requests\UnwrapResponseRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;

$checkout = $argv[1] ?? '';
if (!is_file($checkout . '/vendor/autoload.php')) {
    throw new RuntimeException('Укажите checkout с установленными --no-dev зависимостями');
}
require $checkout . '/vendor/autoload.php';
foreach (['Core/PsrNetworkFailure', 'Core/SequenceHttpClient', 'Requests/CacheProbeRequest', 'Requests/JsonPayloadRequest', 'TestClient'] as $stub) {
    require __DIR__ . '/../Stubs/' . $stub . '.php';
}
if (class_exists('Illuminate\Container\Container') || class_exists('GuzzleHttp\Client')) {
    throw new RuntimeException('В проверяемом checkout присутствуют dev-зависимости');
}
$http = new SequenceHttpClient([
    new PsrNetworkFailure(new Request('GET', 'https://fixture.test')),
    new Response(200, ['Content-Type' => 'application/json'], '{"ok":true}'),
    new Response(200, ['Content-Type' => 'application/json'], '{broken'),
    new Response(200, [], 'null'),
    new Response(200, ['Content-Type' => 'text/plain'], 'raw text'),
    new Response(200, [], 'false'),
]);
$factory = new HttpFactory();
$client = new TestClient(new ClientConfig(
    baseUrl: 'https://fixture.test',
    retry: new RetryConfig(attempts: 2, baseDelay: 0, maxDelay: 0, jitter: false),
), new HttpTransport($http, $factory, $factory));
$request = (new CacheProbeRequest())->setClient($client);
if ($request->dataOrFail() !== ['ok' => true] || $http->calls !== 2) {
    throw new RuntimeException('Нарушена нормализация PSR network failure до retry');
}
$invalid = (new JsonPayloadRequest("\xB1"))->setClient($client)->send()->raw();
if ($invalid->errors->first()?->code->value !== 'serialization_error' || $http->calls !== 2) {
    throw new RuntimeException('Невалидный JSON не остановлен до HTTP');
}
$broken = $request->send()->raw();
if ($broken->errors->first()?->code->value !== 'response_decoding_error' || $broken->response?->status !== 200) {
    throw new RuntimeException('Нарушен контракт ошибки разбора ответа');
}
foreach ([null, 'raw text', false] as $expected) {
    $result = $request->send()->raw();
    if (!$result->isSuccess() || $result->data !== $expected) {
        throw new RuntimeException('Нарушен контракт успешного ответа');
    }
}

require __DIR__ . '/../Stubs/Dto/SimpleResponseDto.php';
require __DIR__ . '/../Stubs/Dto/StringIdentifierDto.php';
require __DIR__ . '/../Stubs/Requests/UnwrapResponseRequest.php';

$config = new ClientConfig(baseUrl: 'https://fixture.test');
$hydrator = new Hydrator(new CastRegistry());
$responseHydrator = new ResponseHydrator(
    $config, $hydrator, new ExtensionRegistry(new CastRegistry(), new HookRegistry(), new AttributeRegistry()),
);
$request = new UnwrapResponseRequest();
$context = new PipelineContext($request, $config, 'standalone');
foreach ([[], ['data' => ['item' => null]]] as $payload) {
    try {
        $responseHydrator->hydrateResponse($request, $context, $payload + ['id' => 7, 'name' => 'fixture']);
        throw new RuntimeException('Неверный unwrap принят');
    } catch (HydrationException $exception) {
        if ($exception->path !== 'data.item') {
            throw new RuntimeException('Не сохранён путь ошибки');
        }
    }
}

$response = (new MockResponse('{"id":9223372036854775808999}'))
    ->toProviderResponse(new PreparedRequest(HttpMethod::GET, 'https://fixture.test'));
$id = $hydrator->hydrate($response->jsonStrict(), StringIdentifierDto::class)->id;
if ($id !== '9223372036854775808999' || $response->json('id') !== $id) {
    throw new RuntimeException('Потеряны цифры идентификатора');
}
try {
    $hydrator->hydrate(['id' => $id, 'name' => 'fixture'], SimpleResponseDto::class);
    throw new RuntimeException('Переполнение int принято');
} catch (HydrationException $exception) {
    if ($exception->reason !== 'integer_out_of_range' || $exception->path !== 'id') {
        throw new RuntimeException('Не сохранена диагностика переполнения');
    }
}
require __DIR__ . '/../Stubs/Dto/HydrationRequiredNullableDto.php';
require __DIR__ . '/../Stubs/Dto/HydrationJsonPayloadDto.php';
require __DIR__ . '/../Stubs/Requests/HydrationProbeRequest.php';
try {
    $hydrator->hydrate([], HydrationRequiredNullableDto::class);
    throw new RuntimeException('Отсутствующий обязательный nullable аргумент принят');
} catch (HydrationException $exception) {
    if ($exception->reason !== 'required_field_missing' || $exception->path !== 'note') {
        throw new RuntimeException('Не сохранена диагностика обязательного поля');
    }
}
if ($hydrator->hydrate(['note' => null], HydrationRequiredNullableDto::class)->note !== null) {
    throw new RuntimeException('Изменено допустимое nullable значение');
}
try {
    $hydrator->hydrate(['payload' => '{broken'], HydrationJsonPayloadDto::class);
    throw new RuntimeException('Невалидный вложенный JSON принят');
} catch (HydrationException $exception) {
    $cause = $exception;
    while ($cause !== null && !$cause instanceof JsonException) {
        $cause = $cause->getPrevious();
    }
    if ($cause === null || $exception->path !== 'payload') {
        throw new RuntimeException('Потеряна причина или путь ошибки JSON');
    }
}
$rawBody = '{"payload":"fixture-secret"}';
$http = new SequenceHttpClient([new Response(200, ['Content-Type' => 'application/json'], $rawBody)]);
$client = new TestClient($config, new HttpTransport($http, $factory, $factory));
$result = (new HydrationProbeRequest(HydrationJsonPayloadDto::class))->setClient($client)->send()->raw();
if ($result->errors->first()?->code->value !== 'hydration_error'
    || $result->errors->first()?->context['path'] !== 'payload'
    || $result->response?->body !== $rawBody || $result->debug !== null
    || str_contains($result->errors->first()->message, 'fixture-secret')) {
    throw new RuntimeException('Нарушен standalone контракт JsonCast/диагностики');
}
echo "Standalone JSON/DTO: обязательные поля, JsonCast и raw response работают без Laravel/Guzzle HTTP Client.\n";
echo "Standalone JSON: прежние контракты, строгий unwrap и точные ID работают без Laravel/Guzzle HTTP Client.\n";
