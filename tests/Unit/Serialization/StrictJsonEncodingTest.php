<?php

declare(strict_types=1);

use ApiSutra\Tests\Support\MappingInvocation;

use ApiSutra\Casts\JsonCast;
use ApiSutra\Enums\Http\FileFormat;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\Serialization\FilePayloadPreparer;

it('строго кодирует JSON-поля multipart и base64', function (FileFormat $format): void {
    expect(fn () => (new FilePayloadPreparer())->prepareBodyAndStream(
        $format, [], ['data' => ['invalid' => "\xB1"]], false, [],
    ))->toThrow(SerializationException::class);
})->with([FileFormat::Multipart, FileFormat::Base64]);

it('JsonCast не превращает ошибку JSON в false', function (): void {
    expect(fn () => MappingInvocation::serialize(new JsonCast(), ['value' => INF]))->toThrow(SerializationException::class)
        ->and(MappingInvocation::serialize(new JsonCast(), false))->toBe('false')
        ->and(MappingInvocation::serialize(new JsonCast(), []))->toBe('[]');
});
