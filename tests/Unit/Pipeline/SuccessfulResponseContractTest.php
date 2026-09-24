<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Hooks\HookInterface;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Tests\Stubs\Core\SequenceHttpClient;
use ApiSutra\Tests\Stubs\Extensions\TestResponseExtension;
use ApiSutra\Tests\Stubs\Requests\CacheProbeRequest;
use ApiSutra\Tests\Stubs\Requests\DownloadCacheRequest;
use ApiSutra\Tests\Stubs\Requests\OptionalResponseRequest;
use ApiSutra\Tests\Stubs\Dto\EmptyStringNullableDto;
use ApiSutra\Tests\Stubs\Requests\UnwrapResponseRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\HttpTransport;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;

it('сохраняет согласованное значение успешного ответа без DTO', function (
    int $status,
    ?string $contentType,
    string $body,
    mixed $expected,
    bool $async,
): void {
    $http = new SequenceHttpClient([new Response($status, $contentType === null ? [] : ['Content-Type' => $contentType], $body)]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), new HttpTransport($http, $factory, $factory));
    $request = (new CacheProbeRequest())->setClient($client);
    $handle = $async ? $request->sendAsync()->wait() : $request->send();

    expect($handle->raw()->isSuccess())->toBeTrue()
        ->and($handle->raw()->data)->toBe($expected)
        ->and($handle->resolved()->data())->toBe($expected)
        ->and($handle->dataOrFail())->toBe($expected)
        ->and($handle->raw()->response->body)->toBe($body)
        ->and($http->calls)->toBe(1);
})->with([
    'JSON null' => [200, 'application/json', 'null', null],
    'empty JSON' => [200, 'application/json', '', null],
    'empty text' => [200, 'text/plain', '', null],
    '204' => [204, null, '', null],
    '204 with body' => [204, 'application/json', '{ignored', null],
    'text' => [200, 'text/plain; charset=utf-8', " hello\n", " hello\n"],
    'text null' => [200, 'text/plain', 'null', 'null'],
    'text JSON' => [200, 'text/plain', '{"ok":true}', '{"ok":true}'],
    'JSON false' => [200, 'application/json', 'false', false],
    'JSON zero' => [200, 'application/json', '0', 0],
    'JSON string' => [200, 'application/json', '"text"', 'text'],
    'JSON list' => [200, 'application/json', '[]', []],
    'JSON object' => [200, 'application/json', '{}', []],
    'JSON suffix' => [200, 'Application/Vnd.Fixture+Json; charset=UTF-8', '{"ok":true}', ['ok' => true]],
    'no type object' => [200, null, '{"ok":true}', ['ok' => true]],
    'no type false' => [200, null, 'false', false],
    'no type null' => [200, null, 'null', null],
    'no type empty' => [200, null, '', null],
])->with([false, true]);

it('не скрывает невалидный JSON без Content-Type или из пробелов', function (?string $contentType, string $body): void {
    $http = new SequenceHttpClient([new Response(200, $contentType === null ? [] : ['Content-Type' => $contentType], $body)]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), new HttpTransport($http, $factory, $factory));
    $result = (new CacheProbeRequest())->setClient($client)->send()->raw();
    expect($result->isFailed())->toBeTrue()
        ->and($result->errors->first()->code->value)->toBe('response_decoding_error')
        ->and($result->response->status)->toBe(200)
        ->and($result->response->body)->toBe($body)
        ->and($http->calls)->toBe(1);
})->with([[null, 'not JSON'], [null, '{broken'], ['application/json', " \n\t"]]);

it('вызывает array hooks для массивов и общие hooks для любого успеха', function (string $body, int $beforeCount): void {
    $http = new SequenceHttpClient([new Response(200, ['Content-Type' => 'application/json'], $body)]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), new HttpTransport($http, $factory, $factory));
    $hooks = [];
    foreach ([Hook::AfterResponse, Hook::BeforeHydrate, Hook::AfterHydrate] as $stage) {
        $hook = new class implements HookInterface {
            public int $calls = 0;
            public function handle(PipelineContext $context): ?array
            {
                $this->calls++;
                return null;
            }
        };
        $hooks[$stage->value] = $hook;
        $client->hooks()->on($stage, $hook);
    }
    $result = (new CacheProbeRequest())->setClient($client)->send()->raw();
    expect($result->isSuccess())->toBeTrue()
        ->and($hooks[Hook::AfterResponse->value]->calls)->toBe(1)
        ->and($hooks[Hook::BeforeHydrate->value]->calls)->toBe($beforeCount)
        ->and($hooks[Hook::AfterHydrate->value]->calls)->toBe(1);
})->with([['null', 0], ['false', 0], ['"text"', 0], ['{}', 1]]);

it('передаёт исходные байты download и обработчику расширения', function (bool $download): void {
    TestResponseExtension::reset();
    $http = new SequenceHttpClient([new Response(200, ['Content-Type' => 'application/json'], '{not JSON')]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test',
        extensions: [new TestResponseExtension()],
    ), new HttpTransport($http, $factory, $factory));
    $request = $download ? new DownloadCacheRequest() : new CacheProbeRequest();
    $result = $request->setClient($client)->withoutCache()->send()->raw();
    expect($result->isSuccess())->toBeTrue();
    if ($download) {
        expect($result->data->content())->toBe('{not JSON');
    } else {
        expect($result->data)->toBe(['handled' => true, 'status' => 200])
            ->and(TestResponseExtension::$bootCount)->toBe(1);
    }
})->with([false, true]);

it('классифицирует скаляр вместо DTO как ошибку гидрации', function (): void {
    $http = new SequenceHttpClient([new Response(200, [], 'false')]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), new HttpTransport($http, $factory, $factory));
    $result = (new UnwrapResponseRequest())->setClient($client)->send()->raw();
    expect($result->isFailed())->toBeTrue()
        ->and($result->errors->first()->code->value)->toBe('hydration_error')
        ->and($result->response->status)->toBe(200);
});

it('сохраняет гидрацию DTO из пустого набора полей', function (int $status, string $body): void {
    $http = new SequenceHttpClient([new Response($status, ['Content-Type' => 'application/json'], $body)]);
    $factory = new HttpFactory();
    $client = new TestClient(new ClientConfig(baseUrl: 'https://fixture.test'), new HttpTransport($http, $factory, $factory));
    $result = (new OptionalResponseRequest())->setClient($client)->send()->raw();
    expect($result->isSuccess())->toBeTrue()
        ->and($result->data)->toBeInstanceOf(EmptyStringNullableDto::class)
        ->and($result->data->name)->toBeNull();
})->with([[200, 'null'], [200, ''], [204, '']]);
