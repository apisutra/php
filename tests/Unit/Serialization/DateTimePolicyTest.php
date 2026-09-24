<?php

declare(strict_types=1);

use ApiSutra\Tests\Support\MappingInvocation;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Casts\DateTimeCast;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\DateTimeHydrationPolicy;
use ApiSutra\Config\DateTimeSerializationPolicy;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\Serialization\DateTimeInvalidBehavior;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Tests\Stubs\Dto\DateTimeCastDto;
use ApiSutra\Tests\Stubs\Dto\DateTimeDto;
use ApiSutra\Tests\Stubs\Dto\DateTimeToOverrideDto;
use ApiSutra\Tests\Stubs\Dto\DateTimeUtcDto;
use ApiSutra\Tests\Stubs\Dto\StrictFormatDateTimeDto;
use ApiSutra\Tests\Stubs\Dto\StrictFormatNullableDateTimeDto;
use ApiSutra\Tests\Stubs\Dto\StrictMissingTimezoneDateTimeDto;
use ApiSutra\Tests\Stubs\Dto\UnionDateTimeLeftDto;
use ApiSutra\Tests\Stubs\Dto\UnionDateTimeRightDto;
use ApiSutra\Tests\Stubs\Requests\DateTimeSerializationRequest;
use ApiSutra\Tests\Stubs\Requests\SerializationRequest;
use ApiSutra\Tests\Stubs\Requests\UnionDateTimeLeftRequest;
use ApiSutra\Tests\Stubs\Requests\UnionDateTimeRightRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('DateTime policy', function () {
    it('использует hydration profile для standalone DTO::from()', function () {
        $dto = DateTimeDto::from([
            'created_at' => '2024-01-01T10:00:00',
        ]);

        expect($dto->createdAt?->getTimezone()->getName())->toBe('Europe/Moscow');
    });

    it('использует тот же hydration profile в pipeline hydration', function () {
        $hydrator = new Hydrator(new CastRegistry());
        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = $hydrator->hydrate(
            ['created_at' => '2024-01-01T10:00:00'],
            DateTimeDto::class,
            $context,
        );

        expect($dto->createdAt?->getTimezone()->getName())->toBe('Europe/Moscow');
    });

    it('сохраняет оффсет по hydration profile', function () {
        $dto = DateTimeDto::from([
            'created_at' => '2024-01-01T10:00:00+03:00',
        ]);

        expect($dto->createdAt?->getOffset())->toBe(10800);
    });

    it('DtoHydrate переопределяет hydration profile для timezone normalization', function () {
        $dto = DateTimeUtcDto::from([
            'created_at' => '2024-01-01T10:00:00+03:00',
        ]);

        expect($dto->createdAt?->getTimezone()->getName())->toBe('UTC');
    });

    it('DtoHydrate включает strictMissingTimezone', function () {
        expect(fn () => StrictMissingTimezoneDateTimeDto::from([
            'created_at' => '2024-01-01T10:00:00',
        ]))->toThrow(HydrationException::class);
    });

    it('DtoHydrate включает strictFormat без fallback', function () {
        expect(fn () => StrictFormatDateTimeDto::from([
            'created_at' => '2024-01-01T10:00:00',
        ]))->toThrow(HydrationException::class);
    });

    it('DtoHydrate поддерживает invalidBehavior=Null для nullable свойства', function () {
        $dto = StrictFormatNullableDateTimeDto::from([
            'created_at' => 'not-a-date',
        ]);

        expect($dto->createdAt)->toBeNull();
    });

    it('toArray использует serialization profile для date-time', function () {
        $dto = new DateTimeDto(new DateTimeImmutable('2024-01-01T10:00:00+03:00'));

        expect($dto->toArray()['created_at'] ?? null)->toBe('2024-01-01 07:00');
    });

    it('DtoSerializer даёт тот же body result, что и toArray', function () {
        $serializer = new DtoSerializer(new CastRegistry());
        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new DateTimeDto(new DateTimeImmutable('2024-01-01T10:00:00+03:00'));
        $data = $serializer->serialize($dto, $context);

        expect($data)->toBe($dto->toArray());
    });

    it('requestDateTime влияет и на query, и на wire body по умолчанию', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            requestDateTime: new DateTimeSerializationPolicy(
                format: 'Y-m-d H:i',
                timezone: 'UTC',
            ),
            environment: Environment::Testing,
        );
        $request = new DateTimeSerializationRequest(
            createdAt: new DateTimeImmutable('2024-01-01T10:00:00+03:00'),
            payloadCreatedAt: new DateTimeImmutable('2024-01-01T10:00:00+03:00'),
        );
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($prepared->url)->toContain('created_at=2024-01-01%2007%3A00')
            ->and($body['payload']['created_at'] ?? null)->toBe('2024-01-01 07:00');
    });

    it('атрибут Cast переопределяет serialization profile', function () {
        $serializer = new DtoSerializer(new CastRegistry());
        $dto = new DateTimeCastDto(new DateTimeImmutable('2024-01-01T10:00:00+03:00'));
        $data = $serializer->serialize($dto);

        expect($data['created_at'] ?? null)->toBe('2024-01-01');
    });

    it('property-level DateTimeTo переопределяет serialization profile', function () {
        $dto = new DateTimeToOverrideDto(new DateTimeImmutable('2024-01-01T10:00:00+03:00'));

        expect($dto->toArray()['created_at'] ?? null)->toBe('2024-01-01T07:00:00+00:00');
    });

    it('client-local registry не влияет на каноническую DTO serialization semantics', function () {
        $casts = new CastRegistry();
        $casts->register(DateTimeImmutable::class, new DateTimeCast(format: 'Y-m-d'));
        $serializer = new DtoSerializer($casts);
        $dto = new DateTimeDto(new DateTimeImmutable('2024-01-01T10:00:00+03:00'));

        expect($serializer->serialize($dto)['created_at'] ?? null)->toBe('2024-01-01 07:00');
    });

    it('union-aware DTO serialization выбирает ветку по runtime-значению', function () {
        $serializer = new DtoSerializer(new CastRegistry());
        $leftString = new UnionDateTimeLeftDto('2024-01-01T10:00:00+03:00');
        $rightDate = new UnionDateTimeRightDto(new DateTimeImmutable('2024-01-01T10:00:00+03:00'));

        $leftData = $serializer->serialize($leftString);
        $rightData = $serializer->serialize($rightDate);

        expect($leftData['created_at'] ?? null)->toBe('2024-01-01T10:00:00+03:00')
            ->and($rightData['created_at'] ?? null)->toBe('2024-01-01 07:00');
    });

    it('union-aware request serialization применяет wire policy к DateTime-ветке', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            requestDateTime: new DateTimeSerializationPolicy(
                format: 'Y-m-d H:i',
                timezone: 'UTC',
            ),
            environment: Environment::Testing,
        );

        $leftRequest = new UnionDateTimeLeftRequest(
            createdAt: '2024-01-01T10:00:00+03:00',
            payloadCreatedAt: '2024-01-01T10:00:00+03:00',
        );
        $leftPrepared = $serializer->serialize(
            $leftRequest,
            new PipelineContext(
                request: $leftRequest,
                config: $config,
                traceId: 'trace',
                role: RequestRole::Root,
            ),
        );
        $leftBody = json_decode($leftPrepared->body ?? '', true);

        $rightRequest = new UnionDateTimeRightRequest(
            createdAt: new DateTimeImmutable('2024-01-01T10:00:00+03:00'),
            payloadCreatedAt: new DateTimeImmutable('2024-01-01T10:00:00+03:00'),
        );
        $rightPrepared = $serializer->serialize(
            $rightRequest,
            new PipelineContext(
                request: $rightRequest,
                config: $config,
                traceId: 'trace',
                role: RequestRole::Root,
            ),
        );
        $rightBody = json_decode($rightPrepared->body ?? '', true);

        expect($leftPrepared->url)->toContain('created_at=2024-01-01T10%3A00%3A00%2B03%3A00')
            ->and($leftBody['payload']['created_at'] ?? null)->toBe('2024-01-01T10:00:00+03:00')
            ->and($rightPrepared->url)->toContain('created_at=2024-01-01%2007%3A00')
            ->and($rightBody['payload']['created_at'] ?? null)->toBe('2024-01-01 07:00');
    });

    it('DateTimeCast::fromSerializationPolicy сериализует только DateTimeInterface', function () {
        $cast = DateTimeCast::fromSerializationPolicy(
            new DateTimeSerializationPolicy(
                format: 'Y-m-d H:i',
                timezone: 'UTC',
            ),
        );

        expect(MappingInvocation::serialize($cast, new DateTimeImmutable('2024-01-01T10:00:00+03:00')))
            ->toBe('2024-01-01 07:00');
    });

    it('DateTimeCast::serialize бросает ошибку для string', function () {
        $cast = DateTimeCast::fromSerializationPolicy(new DateTimeSerializationPolicy());

        expect(fn () => MappingInvocation::serialize($cast, '2024-01-01T10:00:00+03:00'))
            ->toThrow(ConfigurationException::class, 'DateTimeCast::serialize expects DateTimeInterface');
    });

    it('DateTimeCast::fromHydrationPolicy возвращает null для невалидной строки при invalidBehavior=Null', function () {
        $cast = DateTimeCast::fromHydrationPolicy(
            new DateTimeHydrationPolicy(
                format: 'Y-m-d',
                strictFormat: true,
                invalidBehavior: DateTimeInvalidBehavior::Null,
            ),
        );

        expect(MappingInvocation::hydrate($cast, 'not-a-date'))->toBeNull();
    });
});
