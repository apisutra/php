<?php

declare(strict_types=1);

use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\Http\QueryArrayFormat;
use ApiSutra\Enums\Serialization\EnumOutput;
use ApiSutra\Serialization\Serializer;
use ApiSutra\Tests\Stubs\Casts\UppercaseCast;
use ApiSutra\Tests\Stubs\Dto\OutputAddressDto;
use ApiSutra\Tests\Stubs\Dto\OutputItemDto;
use ApiSutra\Tests\Stubs\Dto\OutputUserDto;
use ApiSutra\Tests\Stubs\Dto\NestedWireLeakChildDto;
use ApiSutra\Tests\Stubs\Dto\NestedWireLeakParentDto;
use ApiSutra\Tests\Stubs\Dto\ProfileDrivenOutputDto;
use ApiSutra\Tests\Stubs\Enums\NonBackedStatus;
use ApiSutra\Tests\Stubs\Enums\TestStatus;
use ApiSutra\Tests\Stubs\Enums\TitleStatus;
use ApiSutra\Tests\Stubs\Profiles\ConfiguredDtoSerializationProfile;
use ApiSutra\Tests\Stubs\Profiles\ProfileDrivenDtoSerializationProfile;
use ApiSutra\Tests\Stubs\Requests\DtoBodyMergeRequest;
use ApiSutra\Tests\Stubs\Requests\DtoBodyRequest;
use ApiSutra\Tests\Stubs\Requests\DtoNestedBodyRequest;
use ApiSutra\Tests\Stubs\Requests\CastBodyRequest;
use ApiSutra\Tests\Stubs\Requests\EnumCastBodyRequest;
use ApiSutra\Tests\Stubs\Requests\EnumQueryArrayRequest;
use ApiSutra\Tests\Stubs\Requests\EnumSerializationRequest;
use ApiSutra\Tests\Stubs\Requests\NestedWireLeakBodyRequest;
use ApiSutra\Tests\Stubs\Requests\NonBackedQueryRequest;
use ApiSutra\Tests\Stubs\Requests\ProfileDrivenDtoBodyRequest;
use ApiSutra\Tests\Stubs\Requests\SerializationRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('Serializer', function () {
    it('сериализует path/query/body/header и применяет стратегию имён', function () {
        $casts = new CastRegistry();
        $casts->register('string', new UppercaseCast());

        $serializer = new Serializer($casts);
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::SnakeCase,
            queryArrayFormat: QueryArrayFormat::Repeat,
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(
                new DtoSerializationPolicy(namingStrategy: NamingStrategy::SnakeCase),
            ),
            environment: Environment::Testing,
        );

        $request = new SerializationRequest(
            id: 'A B',
            filters: ['a', 'b'],
            payload: 'payload',
            header: 'header-value',
            plainValue: 'plainValue',
        );

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($prepared->url)->toContain('https://api.test/serialize/A%20B')
            ->and($prepared->url)->toContain('filters=a%2Cb')
            ->and($prepared->headers['X-Custom'] ?? null)->toBe('header-value')
            ->and($body['payload']['data'] ?? null)->toBe('PAYLOAD')
            ->and($body['plain_value'] ?? null)->toBe('PLAINVALUE');
    });

    it('сериализует body DTO через DtoSerializer', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::SnakeCase,
            serializeNulls: false,
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(
                new DtoSerializationPolicy(
                    namingStrategy: NamingStrategy::SnakeCase,
                    serializeNulls: false,
                ),
            ),
            environment: Environment::Testing,
        );

        $request = new DtoBodyRequest(new OutputUserDto(
            userId: 10,
            address: new OutputAddressDto('Moscow', '101000'),
            items: [new OutputItemDto(1, 'First')],
            title: 'hello',
            tags: ['a'],
            plainValue: null,
        ));

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($body['payload']['user_id'] ?? null)->toBe(10)
            ->and($body['payload']['profile']['zip_code'] ?? null)->toBe('101000')
            ->and($body['payload']['items'][0]['label'] ?? null)->toBe('First')
            ->and($body['payload']['title'] ?? null)->toBe('HELLO')
            ->and($body['payload']['meta']['tags'] ?? null)->toBe(['a'])
            ->and(array_key_exists('plain_value', $body['payload'] ?? []))->toBeFalse();
    });

    it('сериализует DTO в nested body пути', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::SnakeCase,
            serializeNulls: false,
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(
                new DtoSerializationPolicy(
                    namingStrategy: NamingStrategy::SnakeCase,
                    serializeNulls: false,
                ),
            ),
            environment: Environment::Testing,
        );

        $request = new DtoNestedBodyRequest(new OutputUserDto(
            userId: 11,
            address: new OutputAddressDto('Kazan', '420000'),
            items: [new OutputItemDto(2, 'Second')],
            title: 'hello',
            tags: [],
            plainValue: null,
        ));

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($body['payload']['data']['user_id'] ?? null)->toBe(11)
            ->and($body['payload']['data']['profile']['city'] ?? null)->toBe('Kazan')
            ->and($body['payload']['data']['items'][0]['label'] ?? null)->toBe('Second');
    });

    it('сериализует nested body с null при serializeNulls=true', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::SnakeCase,
            serializeNulls: true,
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(
                new DtoSerializationPolicy(
                    namingStrategy: NamingStrategy::SnakeCase,
                    serializeNulls: true,
                ),
            ),
            environment: Environment::Testing,
        );

        $request = new DtoNestedBodyRequest(new OutputUserDto(
            userId: 21,
            address: new OutputAddressDto('Tomsk', '634000'),
            items: [],
            title: 'hello',
            tags: [],
            plainValue: null,
        ));

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect(array_key_exists('plain_value', $body['payload']['data'] ?? []))->toBeTrue()
            ->and($body['payload']['data']['plain_value'])->toBeNull();
    });

    it('объединяет несколько nested body полей в один payload', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::SnakeCase,
            serializeNulls: false,
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(
                new DtoSerializationPolicy(
                    namingStrategy: NamingStrategy::SnakeCase,
                    serializeNulls: false,
                ),
            ),
            environment: Environment::Testing,
        );

        $request = new DtoBodyMergeRequest(
            payload: new OutputUserDto(
                userId: 12,
                address: new OutputAddressDto('Perm', '614000'),
                items: [new OutputItemDto(3, 'Third')],
                title: 'hello',
                tags: ['t'],
                plainValue: null,
            ),
            requestId: 'req-1',
        );

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($body['payload']['data']['user_id'] ?? null)->toBe(12)
            ->and($body['payload']['meta']['request_id'] ?? null)->toBe('req-1');
    });

    it('применяет Cast атрибут для body поля запроса', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::None,
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(),
            environment: Environment::Testing,
        );

        $request = new CastBodyRequest('hello');

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($body['payload'] ?? null)->toBe('HELLO');
    });

    it('использует wire-safe body и requestPartsEnumOutput для query/header/path', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::None,
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(
                new DtoSerializationPolicy(enumOutput: EnumOutput::Object),
            ),
            requestPartsEnumOutput: EnumOutput::TitleValueString,
            environment: Environment::Testing,
        );

        $request = new EnumSerializationRequest(
            pathStatus: TitleStatus::Active,
            queryStatus: TitleStatus::Inactive,
            queryStatuses: [TitleStatus::Active, TitleStatus::Inactive],
            headerStatus: TitleStatus::Active,
            bodyStatus: TitleStatus::Active,
            bodyStatuses: [TitleStatus::Active, TitleStatus::Inactive],
        );

        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($prepared->url)->toContain('/enum/Active%7Cactive')
            ->and($prepared->headers['X-Status'] ?? null)->toBe('Active|active')
            ->and(($prepared->meta['query']['status_query']['value'] ?? null))->toBe('Inactive|inactive')
            ->and(($prepared->meta['query']['status_list']['value'] ?? null))->toBe(['Active|active', 'Inactive|inactive'])
            ->and($body['bodyStatus'] ?? null)->toBe('active')
            ->and($body['bodyStatuses'] ?? null)->toBe(['active', 'inactive']);
    });

    it('применяет Cast для enum в body до dto profile enum policy', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::None,
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(
                new DtoSerializationPolicy(enumOutput: EnumOutput::Object),
            ),
            environment: Environment::Testing,
        );

        $request = new EnumCastBodyRequest(TitleStatus::Active);
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($body['status'] ?? null)->toBe('casted:Active');
    });

    it('корректно строит query string для массива enum', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            queryArrayFormat: QueryArrayFormat::Repeat,
            requestPartsEnumOutput: EnumOutput::TitleValueString,
            environment: Environment::Testing,
        );

        $request = new EnumQueryArrayRequest([
            TitleStatus::Active,
            TitleStatus::Inactive,
        ]);
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);

        expect($prepared->url)->toContain('status_list=Active%7Cactive')
            ->and($prepared->url)->toContain('status_list=Inactive%7Cinactive');
    });

    it('сериализует non-backed enum в query', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            queryArrayFormat: QueryArrayFormat::Repeat,
            requestPartsEnumOutput: EnumOutput::TitleValueString,
            environment: Environment::Testing,
        );

        $request = new NonBackedQueryRequest([
            NonBackedStatus::Active,
            NonBackedStatus::Inactive,
        ]);
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);

        expect($prepared->url)->toContain('status_list=Active%20Title%7CActive')
            ->and($prepared->url)->toContain('status_list=Inactive%20Title%7CInactive');
    });

    it('допускает различие между toArray и body payload для profile-bound dto', function () {
        $serializer = new Serializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            dtoSerializationProfile: new ProfileDrivenDtoSerializationProfile(),
            environment: Environment::Testing,
        );

        $dto = new ProfileDrivenOutputDto(
            status: TitleStatus::Active,
            plainValue: null,
        );
        $request = new ProfileDrivenDtoBodyRequest($dto);
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($dto->toArray()['status'] ?? null)->toBe('Active|active')
            ->and($body['payload']['status'] ?? null)->toBe('active');
    });

    it('может явно выровнять wire body policy с dto profile', function () {
        $serializer = new Serializer(new CastRegistry());
        $profile = new ProfileDrivenDtoSerializationProfile();
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            dtoSerializationProfile: $profile,
            wireBodySerializationPolicy: $profile->policy(),
            environment: Environment::Testing,
        );

        $dto = new ProfileDrivenOutputDto(
            status: TitleStatus::Active,
            plainValue: null,
        );
        $request = new ProfileDrivenDtoBodyRequest($dto);
        $context = new PipelineContext(
            request: $request,
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($body['payload'] ?? null)->toBe($dto->toArray());
    });

    it('не протекает в nested dto из wire Value в DX TitleValueString fallback', function () {
        $serializer = new Serializer(new CastRegistry());
        $dto = new NestedWireLeakParentDto(
            nested: new NestedWireLeakChildDto(
                country: TestStatus::Active,
            ),
        );
        $request = new NestedWireLeakBodyRequest($dto);
        $context = new PipelineContext(
            request: $request,
            config: new ClientConfig(
                baseUrl: 'https://api.test',
                environment: Environment::Testing,
            ),
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $prepared = $serializer->serialize($request, $context);
        $body = json_decode($prepared->body ?? '', true);

        expect($dto->toArray()['nested']['country'] ?? null)->toBe('active|active')
            ->and($body['payload']['nested']['country'] ?? null)->toBe('active');
    });
});
