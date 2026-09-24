<?php

declare(strict_types=1);

use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Enums\Serialization\EnumOutput;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Serialization\DtoSerializer;
use ApiSutra\Tests\Stubs\Casts\UppercaseCast;
use ApiSutra\Tests\Stubs\Dto\ArrayValue;
use ApiSutra\Tests\Stubs\Dto\MapBidirectionalDto;
use ApiSutra\Tests\Stubs\Dto\MapPriorityDto;
use ApiSutra\Tests\Stubs\Dto\OutputAddressDto;
use ApiSutra\Tests\Stubs\Dto\OutputArrayDto;
use ApiSutra\Tests\Stubs\Dto\OutputDotNullDto;
use ApiSutra\Tests\Stubs\Dto\OutputBadTitleEnumDto;
use ApiSutra\Tests\Stubs\Dto\OutputEnumCastDto;
use ApiSutra\Tests\Stubs\Dto\OutputEnumDto;
use ApiSutra\Tests\Stubs\Dto\OutputEnumFallbackDto;
use ApiSutra\Tests\Stubs\Dto\OutputItemDto;
use ApiSutra\Tests\Stubs\Dto\OutputJsonDto;
use ApiSutra\Tests\Stubs\Dto\OutputNullArrayDto;
use ApiSutra\Tests\Stubs\Dto\OutputNonBackedEnumDto;
use ApiSutra\Tests\Stubs\Dto\OutputRegistryCastDto;
use ApiSutra\Tests\Stubs\Dto\OutputStringableEnumDto;
use ApiSutra\Tests\Stubs\Dto\OutputUserDto;
use ApiSutra\Tests\Stubs\Dto\ProfileDrivenOutputDto;
use ApiSutra\Tests\Stubs\Dto\ProfileDrivenOverrideDto;
use ApiSutra\Tests\Stubs\Dto\UnsupportedPrivateDto;
use ApiSutra\Tests\Stubs\Enums\TestStatus;
use ApiSutra\Tests\Stubs\Enums\BadTitleStatus;
use ApiSutra\Tests\Stubs\Enums\NonBackedStatus;
use ApiSutra\Tests\Stubs\Enums\StringableTitleStatus;
use ApiSutra\Tests\Stubs\Enums\TitleStatus;
use ApiSutra\Tests\Stubs\Profiles\AlternateDtoSerializationProfile;
use ApiSutra\Tests\Stubs\Profiles\ConfiguredDtoSerializationProfile;
use ApiSutra\Tests\Stubs\Profiles\ProfileDrivenDtoSerializationProfile;
use ApiSutra\Tests\Stubs\Dto\JsonValue;
use ApiSutra\Tests\Stubs\Requests\SerializationRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('DtoSerializer', function () {
    it('сериализует DTO с To, вложенными DTO и кастами', function () {
        $serializer = new DtoSerializer(new CastRegistry());
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

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputUserDto(
            userId: 10,
            address: new OutputAddressDto('Moscow', '101000'),
            items: [new OutputItemDto(1, 'First')],
            title: 'hello',
            tags: ['a', 'b'],
            plainValue: 'plainValue',
        );

        $data = $serializer->serialize($dto, $context);

        expect($data['user_id'] ?? null)->toBe(10);
        expect($data['profile']['city'] ?? null)->toBe('Moscow');
        expect($data['profile']['zip_code'] ?? null)->toBe('101000');
        expect($data['items'][0]['id'] ?? null)->toBe(1);
        expect($data['items'][0]['label'] ?? null)->toBe('First');
        expect($data['title'] ?? null)->toBe('HELLO');
        expect($data['meta']['tags'] ?? null)->toBe(['a', 'b']);
        expect($data['plain_value'] ?? null)->toBe('plainValue');
    });

    it('сериализует Map двусторонне и оставляет NamingStrategy fallback для остальных полей', function () {
        $serializer = new DtoSerializer(new CastRegistry());
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

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new MapBidirectionalDto(
            queryNumber: '42',
            plainValue: 'ok',
        );

        $data = $serializer->serialize($dto, $context);

        expect($data['query_num'] ?? null)->toBe('42')
            ->and($data['plain_value'] ?? null)->toBe('ok')
            ->and(array_key_exists('query_number', $data))->toBeFalse();
    });

    it('дает To приоритет над Map при сериализации', function () {
        $serializer = new DtoSerializer(new CastRegistry());
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

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new MapPriorityDto(
            fromPreferred: 'from',
            toPreferred: 'to',
            ownershipType: 'lease',
        );

        $data = $serializer->serialize($dto, $context);

        expect($data['shared_key'] ?? null)->toBe('from')
            ->and($data['to_key'] ?? null)->toBe('to')
            ->and($data['ownersheep_type'] ?? null)->toBe('lease')
            ->and(array_key_exists('to_preferred', $data))->toBeFalse();
    });

    it('включает null поля при serializeNulls=true', function () {
        $serializer = new DtoSerializer(new CastRegistry());
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

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputUserDto(
            userId: 10,
            address: new OutputAddressDto('Moscow', '101000'),
            items: [],
            title: 'hello',
            tags: [],
            plainValue: null,
        );

        $data = $serializer->serialize($dto, $context);

        expect(array_key_exists('plain_value', $data))->toBeTrue();
        expect($data['plain_value'])->toBeNull();
    });

    it('применяет stable cast из dto profile при сериализации', function () {
        $casts = new CastRegistry();
        $casts->register('string', new UppercaseCast());
        $serializer = new DtoSerializer($casts);
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            namingStrategy: NamingStrategy::SnakeCase,
            serializeNulls: false,
            environment: Environment::Testing,
        );

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputRegistryCastDto('lower');
        $data = $serializer->serialize($dto, $context);

        expect($data['code'] ?? null)->toBe('LOWER');
    });

    it('сериализует JsonSerializable значения', function () {
        $serializer = new DtoSerializer(new CastRegistry());
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

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputJsonDto(new JsonValue('ok'));
        $data = $serializer->serialize($dto, $context);

        expect($data['payload']['value'] ?? null)->toBe('ok');
    });

    it('сохраняет null элементы массивов при serializeNulls=false', function () {
        $serializer = new DtoSerializer(new CastRegistry());
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

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputNullArrayDto([null, 'a']);
        $data = $serializer->serialize($dto, $context);

        expect($data['items'][0] ?? null)->toBeNull();
        expect($data['items'][1] ?? null)->toBe('a');
    });

    it('сериализует mixed массивы с DTO и скалярами', function () {
        $serializer = new DtoSerializer(new CastRegistry());
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

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputNullArrayDto([
            new OutputItemDto(1, 'First'),
            'raw',
        ]);

        $data = $serializer->serialize($dto, $context);

        expect($data['items'][0]['label'] ?? null)->toBe('First');
        expect($data['items'][1] ?? null)->toBe('raw');
    });

    it('не добавляет dot-path при null и serializeNulls=false', function () {
        $serializer = new DtoSerializer(new CastRegistry());
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

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputDotNullDto(null);
        $data = $serializer->serialize($dto, $context);

        expect(array_key_exists('meta', $data))->toBeFalse();
    });

    it('добавляет dot-path при null и serializeNulls=true', function () {
        $serializer = new DtoSerializer(new CastRegistry());
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

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputDotNullDto(null);
        $data = $serializer->serialize($dto, $context);

        expect(array_key_exists('meta', $data))->toBeTrue();
        expect(array_key_exists('value', $data['meta']['inner'] ?? []))->toBeTrue();
        expect($data['meta']['inner']['value'])->toBeNull();
    });

    it('сериализует объект с toArray внутри DTO', function () {
        $serializer = new DtoSerializer(new CastRegistry());
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

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputArrayDto(new ArrayValue('ok'));
        $data = $serializer->serialize($dto, $context);

        expect($data['payload']['value'] ?? null)->toBe('ok');
    });

    it('сериализует enum по dto profile в объект с title', function () {
        $serializer = new DtoSerializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(
                new DtoSerializationPolicy(enumOutput: EnumOutput::Object),
            ),
            environment: Environment::Testing,
        );

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputEnumDto(TitleStatus::Active);
        $data = $serializer->serialize($dto, $context);

        expect($data['status']['value'] ?? null)->toBe('active')
            ->and($data['status']['title'] ?? null)->toBe('Active');
    });

    it('делает fallback для Object при отсутствии title и strictMode=false', function () {
        $serializer = new DtoSerializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(
                new DtoSerializationPolicy(
                    enumOutput: EnumOutput::Object,
                    strictEnums: false,
                ),
            ),
            environment: Environment::Testing,
        );

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputEnumFallbackDto(TestStatus::Active);
        $data = $serializer->serialize($dto, $context);

        expect($data['status']['value'] ?? null)->toBe('active')
            ->and($data['status']['title'] ?? null)->toBe('active');
    });

    it('делает fallback для TitleValueString при отсутствии title и strictMode=false', function () {
        $serializer = new DtoSerializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(
                new DtoSerializationPolicy(
                    enumOutput: EnumOutput::TitleValueString,
                    strictEnums: false,
                ),
            ),
            environment: Environment::Testing,
        );

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputEnumFallbackDto(TestStatus::Active);
        $data = $serializer->serialize($dto, $context);

        expect($data['status'] ?? null)->toBe('active|active');
    });

    it('бросает исключение при отсутствии title и strictMode=true', function () {
        $serializer = new DtoSerializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(
                new DtoSerializationPolicy(
                    enumOutput: EnumOutput::Object,
                    strictEnums: true,
                ),
            ),
            environment: Environment::Testing,
        );

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputEnumFallbackDto(TestStatus::Active);

        expect(fn () => $serializer->serialize($dto, $context))
            ->toThrow(ConfigurationException::class, 'Enum ' . TestStatus::class . ' must have a title() method');
    });

    it('применяет Cast поверх dto profile enum policy', function () {
        $serializer = new DtoSerializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(
                new DtoSerializationPolicy(enumOutput: EnumOutput::Object),
            ),
            environment: Environment::Testing,
        );

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputEnumCastDto(TitleStatus::Active);
        $data = $serializer->serialize($dto, $context);

        expect($data['status'] ?? null)->toBe('casted:Active');
    });

    it('сериализует non-backed enum через Value', function () {
        $serializer = new DtoSerializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(
                new DtoSerializationPolicy(enumOutput: EnumOutput::Value),
            ),
            environment: Environment::Testing,
        );

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputNonBackedEnumDto(NonBackedStatus::Active);
        $data = $serializer->serialize($dto, $context);

        expect($data['status'] ?? null)->toBe('Active');
    });

    it('сериализует non-backed enum через TitleValueString', function () {
        $serializer = new DtoSerializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(
                new DtoSerializationPolicy(enumOutput: EnumOutput::TitleValueString),
            ),
            environment: Environment::Testing,
        );

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputNonBackedEnumDto(NonBackedStatus::Active);
        $data = $serializer->serialize($dto, $context);

        expect($data['status'] ?? null)->toBe('Active Title|Active');
    });

    it('поддерживает title как Stringable', function () {
        $serializer = new DtoSerializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(
                new DtoSerializationPolicy(enumOutput: EnumOutput::Object),
            ),
            environment: Environment::Testing,
        );

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputStringableEnumDto(StringableTitleStatus::Active);
        $data = $serializer->serialize($dto, $context);

        expect($data['status']['title'] ?? null)->toBe('Active Stringable')
            ->and($data['status']['value'] ?? null)->toBe('active');
    });

    it('бросает исключение если title не строка при strictMode=true', function () {
        $serializer = new DtoSerializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            dtoSerializationProfile: new ConfiguredDtoSerializationProfile(
                new DtoSerializationPolicy(
                    enumOutput: EnumOutput::Object,
                    strictEnums: true,
                ),
            ),
            environment: Environment::Testing,
        );

        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        $dto = new OutputBadTitleEnumDto(BadTitleStatus::Active);

        expect(fn () => $serializer->serialize($dto, $context))
            ->toThrow(ConfigurationException::class, 'Enum ' . BadTitleStatus::class . ' must return a string from title()');
    });

    it('использует profile-bound DTO policy в toArray без context', function () {
        $dto = new ProfileDrivenOutputDto(
            status: TitleStatus::Active,
            plainValue: null,
        );

        expect($dto->toArray())->toBe([
            'status' => 'Active|active',
            'plain_value' => null,
        ]);
    });

    it('применяет class-level DTO override поверх profile', function () {
        $dto = new ProfileDrivenOverrideDto(
            status: TitleStatus::Active,
            plainValue: null,
        );

        expect($dto->toArray())->toBe([
            'status' => [
                'value' => 'active',
                'title' => 'Active',
            ],
        ]);
    });

    it('падает при конфликте profile DTO и client config', function () {
        $serializer = new DtoSerializer(new CastRegistry());
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            dtoSerializationProfile: new AlternateDtoSerializationProfile(),
            environment: Environment::Testing,
        );
        $context = new PipelineContext(
            request: new SerializationRequest('id', [], 'payload', 'header', 'plainValue'),
            config: $config,
            traceId: 'trace',
            role: RequestRole::Root,
        );

        expect(fn () => $serializer->serialize(
            new ProfileDrivenOutputDto(status: TitleStatus::Active),
            $context,
        ))->toThrow(ConfigurationException::class, 'DTO profile conflict');
    });

    it('падает на unsupported dto shape с непубличным свойством', function () {
        $serializer = new DtoSerializer(new CastRegistry());

        expect(fn () => $serializer->serialize(new UnsupportedPrivateDto('secret')))
            ->toThrow(ConfigurationException::class, 'DTO serializer supports only public data properties');
    });
});
