<?php

declare(strict_types=1);

use ApiSutra\Tests\Support\MappingInvocation;

use ApiSutra\Casts\CastRegistry;
use ApiSutra\Casts\IntegerCast;
use ApiSutra\Casts\JsonCast;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Diagnostics\RedactionPolicy;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Dto\IdentifierEnvelopeDto;
use ApiSutra\Tests\Stubs\Dto\IdentifierTypesDto;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use ApiSutra\Tests\Stubs\Dto\StringIdentifierDto;
use ApiSutra\Tests\Stubs\Requests\IntegerCastRequest;
use ApiSutra\Tests\Stubs\Requests\UnwrapResponseRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Http\PreparedRequest;

it('не подставляет корень при missing или неверной форме unwrap', function (array $envelope, string $reason, string $actual): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success($envelope + ['id' => 7, 'name' => 'outer-secret-fixture'])]);
    $client = new TestClient(new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing), $transport);
    $result = (new UnwrapResponseRequest())->setClient($client)->send()->raw();
    expect($result->isFailed())->toBeTrue()
        ->and($result->response->status)->toBe(200)
        ->and($result->errors->first()->code->value)->toBe('hydration_error')
        ->and($result->errors->first()->context)->toMatchArray(['reason' => $reason, 'path' => 'data.item', 'actual' => $actual])
        ->and($result->errors->first()->message)->not->toContain('outer-secret-fixture')
        ->and($transport->getRecorded())->toHaveCount(1);
})->with([
    [[], 'unwrap_path_missing', 'missing'],
    [['data' => []], 'unwrap_path_missing', 'missing'],
    [['data' => null], 'unwrap_path_missing', 'missing'],
    [['data' => 5], 'unwrap_path_missing', 'missing'],
    [['data' => ['item' => null]], 'unexpected_response_shape', 'null'],
    [['data' => ['item' => false]], 'unexpected_response_shape', 'bool'],
    [['data' => ['item' => 0]], 'unexpected_response_shape', 'int'],
    [['data' => ['item' => 'fixture-secret']], 'unexpected_response_shape', 'string'],
]);

it('сохраняет все цифры больших JSON integer в DTO и вспомогательном JSON', function (): void {
    $id = '9223372036854775808999';
    $body = '{"id":' . $id . ',"token":"fixture-secret"}';
    $response = MockResponse::make($body)->toProviderResponse(new PreparedRequest(HttpMethod::GET, 'https://api.test'));
    $dto = (new Hydrator(new CastRegistry()))->hydrate($response->jsonStrict(), StringIdentifierDto::class);
    expect($dto->id)->toBe($id)
        ->and($response->json('id'))->toBe($id)
        ->and(MappingInvocation::hydrate(new JsonCast(), $body)['id'])->toBe($id)
        ->and((new RedactionPolicy())->body($body))->toBe('{"id":"' . $id . '","token":"***"}');
});

it('отклоняет переполнение int при гидратации и явном IntegerCast', function (mixed $value): void {
    $hydrator = new Hydrator(new CastRegistry());
    expect(fn () => $hydrator->hydrate(['id' => $value, 'name' => 'fixture'], SimpleResponseDto::class))->toThrow(HydrationException::class)
        ->and(fn () => MappingInvocation::hydrate(new IntegerCast(), $value))->toThrow(HydrationException::class)
        ->and(fn () => MappingInvocation::serialize(new IntegerCast(), $value))->toThrow(SerializationException::class);
})->with([
    '9223372036854775808999', '-9223372036854775808999',
    PHP_INT_SIZE === 8 ? '9223372036854775808' : '2147483648',
    PHP_INT_SIZE === 8 ? '-9223372036854775809' : '-2147483649',
    '+0009223372036854775808999', '-0009223372036854775808999',
    '1e100', '-1e100', 1e100, -1e100, INF, NAN,
]);


it('сохраняет границы int и обычные числовые преобразования', function (mixed $value, int $expected): void {
    $dto = (new Hydrator(new CastRegistry()))->hydrate(['id' => $value, 'name' => 'fixture'], SimpleResponseDto::class);
    expect($dto->id)->toBe($expected)
        ->and(MappingInvocation::hydrate(new IntegerCast(), $value))->toBe($expected)
        ->and(MappingInvocation::serialize(new IntegerCast(), $value))->toBe($expected);
})->with([
    [PHP_INT_MAX, PHP_INT_MAX], [PHP_INT_MIN, PHP_INT_MIN], [(string) PHP_INT_MAX, PHP_INT_MAX],
    [(string) PHP_INT_MIN, PHP_INT_MIN], ['00042', 42], ['-00042', -42], ['+42', 42],
    [' 42 ', 42], ['42.0', 42], ['4.2e1', 42], [42.0, 42], ['-0', 0],
]);

it('сохраняет строки union mixed и nullable поле без настройки клиента', function (): void {
    $id = '9223372036854775808999';
    $hydrator = new Hydrator(new CastRegistry());
    $dto = $hydrator->hydrate(['stringId' => $id, 'unionId' => $id, 'rawId' => $id, 'approximate' => '1e100'], IdentifierTypesDto::class);
    expect($dto->stringId)->toBe($id)->and($dto->unionId)->toBe($id)->and($dto->rawId)->toBe($id)
        ->and($dto->nullableId)->toBeNull()->and($dto->approximate)->toBe(1e100);
    expect(fn () => $hydrator->hydrate(['stringId' => $id, 'unionId' => $id, 'rawId' => $id, 'nullableId' => $id], IdentifierTypesDto::class))
        ->toThrow(HydrationException::class);
    $empty = $hydrator->hydrate(['item' => null, 'items' => []], IdentifierEnvelopeDto::class);
    expect($empty->item)->toBeNull()->and($empty->items)->toBe([]);
});

it('показывает безопасный путь вложенного поля и элемента коллекции', function (array $payload, string $path): void {
    try {
        (new Hydrator(new CastRegistry()))->hydrate($payload, IdentifierEnvelopeDto::class);
        test()->fail('Переполнение принято');
    } catch (HydrationException $exception) {
        expect($exception->context())->toMatchArray(['reason' => 'integer_out_of_range', 'path' => $path, 'expected' => 'int', 'actual' => 'string'])
            ->and($exception->getMessage())->not->toContain('9223372036854775808999', 'fixture-secret');
    }
})->with([
    [['item' => ['id' => '9223372036854775808999', 'name' => 'fixture-secret']], 'item.id'],
    [['items' => [['id' => 1, 'name' => 'ok'], ['id' => '9223372036854775808999', 'name' => 'fixture-secret']]], 'items[1].id'],
]);

it('сохраняет новые ошибки через result и promise API', function (bool $async): void {
    $transport = new MockTransport();
    $body = '{"data":{"item":{"id":9223372036854775808999,"name":"fixture-secret"}}}';
    $transport->fake(['*' => MockResponse::make($body)]);
    $config = new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing);
    $request = (new UnwrapResponseRequest())->setClient(new TestClient($config, $transport));
    $handle = $async ? $request->sendAsync()->wait() : $request->send();
    expect($handle->raw()->errors->first()->context)->toMatchArray(['reason' => 'integer_out_of_range', 'path' => 'data.item.id'])
        ->and($handle->raw()->response->body)->toBe($body)
        ->and($handle->resolved()->error()->sdkCode->value)->toBe('hydration_error')
        ->and(fn () => $handle->dataOrFail())->toThrow(HydrationException::class)
        ->and($transport->getRecorded())->toHaveCount(1);
    $throwing = (new UnwrapResponseRequest())->setClient(new TestClient($config->with(throwOnErrors: true), $transport));
    expect(static function () use ($throwing, $async): void {
        ($async ? $throwing->sendAsync()->wait() : $throwing->send())->raw();
    })->toThrow(HydrationException::class);
})->with([false, true]);

it('не отправляет IntegerCast overflow в HTTP', function (): void {
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::success()]);
    $request = (new IntegerCastRequest('9223372036854775808999'))->setClient(new TestClient(
        new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing), $transport,
    ));
    expect($request->send()->raw()->errors->first()->code->value)->toBe('serialization_error')
        ->and($transport->getRecorded())->toHaveCount(0);
});

it('декодирует integer литералы без потери цифр и не меняет дроби', function (string $body, mixed $expected): void {
    $response = MockResponse::make($body)->toProviderResponse(new PreparedRequest(HttpMethod::GET, 'https://api.test'));
    expect($response->jsonStrict())->toBe($expected)->and($response->json())->toBe($expected);
})->with([
    [(string) PHP_INT_MAX, PHP_INT_MAX], [(string) PHP_INT_MIN, PHP_INT_MIN],
    ['9223372036854775808', '9223372036854775808'], ['-9223372036854775809', '-9223372036854775809'],
    ['99999999999999999999999999999999999999999999999999', '99999999999999999999999999999999999999999999999999'],
    ['"9223372036854775808"', '9223372036854775808'], ['1.25', 1.25], ['1e30', 1e30],
    ['[9223372036854775808]', ['9223372036854775808']], ['false', false], ['null', null],
]);

it('сохраняет permissive malformed JSON и приоритет HTTP ошибки', function (): void {
    $response = MockResponse::make('{broken', 503)->toProviderResponse(new PreparedRequest(HttpMethod::GET, 'https://api.test'));
    expect($response->json())->toBeNull()->and(fn () => MappingInvocation::hydrate(new JsonCast(), '{broken'))->toThrow(HydrationException::class)
        ->and($response->errorMessage())->toBe('HTTP 503');
});
