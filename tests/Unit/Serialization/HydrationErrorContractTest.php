<?php

declare(strict_types=1);

use ApiSutra\Tests\Support\MappingInvocation;

use ApiSutra\Casts\JsonCast;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Testing\MockResponse;
use ApiSutra\Tests\Stubs\Dto\HydrationJsonPayloadDto;
use ApiSutra\Tests\Stubs\Dto\HydrationRequiredNullableDto;
use ApiSutra\Tests\Stubs\Dto\IdentifierEnvelopeDto;
use ApiSutra\Tests\Stubs\Dto\InheritedRequiredPayloadDto;
use ApiSutra\Tests\Stubs\Dto\PolymorphicOwnersArrayKeyErrorDto;
use ApiSutra\Tests\Stubs\Dto\SimpleResponseDto;
use ApiSutra\Tests\Stubs\Dto\StrictFormatDateTimeDto;
use ApiSutra\Tests\Stubs\Requests\HydrationProbeRequest;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Transport\MockTransport;
use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;

it('доставляет безопасную ошибку поля и сохраняет raw HTTP без debug', function (string $type, array $payload, string $reason, string $path): void {
    $logger = new class extends AbstractLogger {
        /** @var list<array<string, mixed>> */
        public array $entries = [];
        public function log(mixed $level, Stringable|string $message, array $context = []): void
        {
            $this->entries[] = ['message' => (string) $message, 'context' => $context];
        }
    };
    $body = json_encode($payload + ['secret' => 'fixture-secret'], JSON_THROW_ON_ERROR);
    $transport = new MockTransport();
    $transport->fake(['*' => MockResponse::make($body)]);
    $client = new TestClient(new ClientConfig(
        baseUrl: 'https://fixture.test', environment: Environment::Testing, debug: false,
        logger: $logger, logLevel: LogLevel::ERROR,
    ), $transport);
    $handle = (new HydrationProbeRequest($type))->setClient($client)->send();
    $result = $handle->raw();
    expect($result->isFailed())->toBeTrue()
        ->and($result->errors->first()->code->value)->toBe('hydration_error')
        ->and($result->errors->first()->context)->toMatchArray(['path' => $path, 'reason' => $reason])
        ->and($result->response->body)->toBe($body)
        ->and($result->response->status)->toBe(200)
        ->and($transport->getRecorded())->toHaveCount(1)
        ->and(json_encode($result->errors->first()->context))->not->toContain('fixture-secret')
        ->and($result->errors->first()->message)->not->toContain('fixture-secret')
        ->and(json_encode($logger->entries))->not->toContain('fixture-secret')
        ->and($logger->entries[0]['context'])->toMatchArray(['path' => $path, 'reason' => $reason, 'httpStatus' => 200, 'traceId' => $result->traceId])
        ->and(fn () => $handle->dataOrFail())->toThrow(HydrationException::class);
    try {
        $type::from($payload);
        test()->fail('Данные приняты');
    } catch (HydrationException $exception) {
        expect($exception->reason)->toBe($reason)->and($exception->path)->toBe($path);
    }
})->with([
    [SimpleResponseDto::class, ['name' => 'fixture'], 'required_field_missing', 'id'],
    [SimpleResponseDto::class, ['id' => null, 'name' => 'fixture'], 'null_not_allowed', 'id'],
    [SimpleResponseDto::class, ['id' => [], 'name' => 'fixture'], 'invalid_field_type', 'id'],
    [HydrationRequiredNullableDto::class, [], 'required_field_missing', 'note'],
    [InheritedRequiredPayloadDto::class, ['status' => 'active'], 'required_field_missing', 'result'],
    [IdentifierEnvelopeDto::class, ['items' => [['id' => 1, 'name' => 'ok'], ['id' => [], 'name' => 'bad']]], 'invalid_field_type', 'items[1].id'],
    [IdentifierEnvelopeDto::class, ['items' => [false]], 'unexpected_response_shape', 'items[0]'],
    [HydrationJsonPayloadDto::class, ['payload' => '{"token":"fixture-secret"'], 'invalid_json', 'payload'],
    [StrictFormatDateTimeDto::class, ['created_at' => 'fixture-secret'], 'invalid_datetime', 'createdAt'],
    [PolymorphicOwnersArrayKeyErrorDto::class, ['owners' => [['fixture-secret' => ['id' => 1]]]], 'unknown_nested_variant', 'owners[0]'],
]);

it('JsonCast отказывает при неверном JSON с исходным JsonException', function (string $value): void {
    try {
        MappingInvocation::hydrate(new JsonCast(), $value);
        test()->fail('Неверный JSON принят');
    } catch (HydrationException $exception) {
        expect($exception->reason)->toBe('invalid_json')->and($exception->getPrevious())->toBeInstanceOf(JsonException::class);
    }
})->with(['', ' ', '{broken', "\xB1", str_repeat('[', 513) . str_repeat(']', 513)]);
