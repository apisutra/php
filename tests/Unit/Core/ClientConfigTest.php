<?php

declare(strict_types=1);

use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\CredentialsEnrichmentConfig;
use ApiSutra\Config\CredentialsScopeConfig;
use ApiSutra\Config\DateTimeSerializationPolicy;
use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Contracts\Interfaces\Continuation\ContinuationModeApplicatorInterface;
use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;
use ApiSutra\Tests\Stubs\Enrichment\TestRequestPartsEnricher;
use ApiSutra\Casts\BooleanCast;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Enums\Serialization\EnumOutput;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Result\ContinuationTokenExtractorInterface;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResultMetaExtractorInterface;
use ApiSutra\Serialization\VO\RequestPartsBag;
use ApiSutra\Tests\Stubs\TestClient;
use ApiSutra\Tests\Stubs\Requests\ContinuationPollRequest;
use ApiSutra\Transport\MockTransport;
use ApiSutra\VO\Pipeline\PipelineContext;

describe('ClientConfig и AbstractClient', function () {
    it('отключает кеш метаданных в Testing и включает в Production', function () {
        $clientTesting = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Testing),
            new MockTransport(),
        );
        $clientProd = new TestClient(
            new ClientConfig(baseUrl: 'https://api.test', environment: Environment::Production),
            new MockTransport(),
        );

        expect($clientTesting->getAttributeMetadataCache()->isEnabled())->toBeFalse()
            ->and($clientProd->getAttributeMetadataCache()->isEnabled())->toBeTrue();
    });

    it('ClientConfig::with переопределяет значения', function () {
        $config = new ClientConfig(baseUrl: 'https://api.test', timeout: 30, connectTimeout: 5);

        $updated = $config->with(baseUrl: 'https://api.next', timeout: 10);

        expect($updated->baseUrl)->toBe('https://api.next')
            ->and($updated->timeout)->toBe(10)
            ->and($updated->connectTimeout)->toBe(5);
    });

    it('валидирует baseUrl', function () {
        expect(fn () => new ClientConfig(baseUrl: ''))
            ->toThrow(ConfigurationException::class, 'ClientConfig.baseUrl must not be empty');
    });

    it('валидирует числовые параметры', function (array $overrides, string $message) {
        $data = array_merge(['baseUrl' => 'https://api.test'], $overrides);

        expect(fn () => new ClientConfig(...$data))
            ->toThrow(ConfigurationException::class, $message);
    })->with([
        'timeout' => [['timeout' => -1], 'ClientConfig.timeout must be >= 0'],
        'connectTimeout' => [['connectTimeout' => -1], 'ClientConfig.connectTimeout must be >= 0'],
        'delay' => [['delay' => -1], 'ClientConfig.delay must be >= 0'],
        'authRetryAttempts' => [['authRetryAttempts' => -1], 'ClientConfig.authRetryAttempts must be >= 0'],
    ]);

    it('валидирует idempotencyHeader', function () {
        expect(fn () => new ClientConfig(baseUrl: 'https://api.test', idempotencyHeader: ' '))
            ->toThrow(ConfigurationException::class, 'ClientConfig.idempotencyHeader must not be empty');
    });

    it('валидирует requestPartsEnumOutput', function () {
        expect(fn () => new ClientConfig(
            baseUrl: 'https://api.test',
            requestPartsEnumOutput: EnumOutput::Object,
        ))->toThrow(ConfigurationException::class, 'ClientConfig.requestPartsEnumOutput cannot be Object for query/header/path');
    });

    it('валидирует requestDateTime timezone', function () {
        expect(fn () => new ClientConfig(
            baseUrl: 'https://api.test',
            requestDateTime: new DateTimeSerializationPolicy(timezone: 'Bad/Timezone'),
        ))->toThrow(ConfigurationException::class, 'ClientConfig.requestDateTime.timezone contains an invalid timezone: Bad/Timezone');
    });

    it('валидирует casts', function (array $casts, string $message) {
        expect(fn () => new ClientConfig(baseUrl: 'https://api.test', casts: $casts))
            ->toThrow(ConfigurationException::class, $message);
    })->with([
        'class not found' => [['bad' => 'MissingCast'], 'ClientConfig.casts[bad] class MissingCast not found'],
        'class without interface' => [['bad' => stdClass::class], 'ClientConfig.casts[bad] ' . stdClass::class . ' must implement HydrationCastInterface or SerializationCastInterface'],
        'invalid object' => [['bad' => new stdClass()], 'ClientConfig.casts[bad] contains an invalid value'],
    ]);

    it('принимает валидные casts', function () {
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            casts: [
                'bool' => BooleanCast::class,
                'bool_obj' => new BooleanCast(),
            ],
        );

        expect($config->casts)->toHaveCount(2);
    });

    it('валидирует requestEnrichers и credentialsConfig.scopes', function () {
        expect(fn () => new ClientConfig(
            baseUrl: 'https://api.test',
            requestEnrichers: [new stdClass()],
        ))->toThrow(
            ConfigurationException::class,
            'ClientConfig.requestEnrichers[0] must implement RequestPartsEnricherInterface',
        );

        expect(fn () => new ClientConfig(
            baseUrl: 'https://api.test',
            credentialsConfig: new CredentialsEnrichmentConfig(
                scopes: ['system' => new stdClass()],
            ),
        ))->toThrow(
            ConfigurationException::class,
            'ClientConfig.credentialsConfig.scopes[system] must be CredentialsScopeConfig',
        );

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            requestEnrichers: [new TestRequestPartsEnricher('trace', 'abc')],
            credentialsConfig: new CredentialsEnrichmentConfig(
                scopes: ['system' => new CredentialsScopeConfig()],
            ),
        );

        expect($config->requestEnrichers)->toHaveCount(1)
            ->and($config->credentialsConfig)->not->toBeNull();
    });

    it('принимает continuationTokenExtractor и сохраняет в with()', function () {
        $extractor = new class implements ContinuationTokenExtractorInterface {
            #[\Override]
            public function extract(ExecutionResult $result): ?string
            {
                return 'tok-1';
            }
        };

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            continuationTokenExtractor: $extractor,
        );
        $updated = $config->with(timeout: 5);

        expect($config->continuationTokenExtractor)->toBe($extractor)
            ->and($updated->continuationTokenExtractor)->toBe($extractor)
            ->and($updated->timeout)->toBe(5);
    });

    it('сохраняет wireBodySerializationPolicy в with()', function () {
        $wirePolicy = new DtoSerializationPolicy(enumOutput: EnumOutput::Object);
        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            wireBodySerializationPolicy: $wirePolicy,
        );
        $updated = $config->with(timeout: 5);

        expect($config->wireBodySerializationPolicy)->toBe($wirePolicy)
            ->and($updated->wireBodySerializationPolicy)->toBe($wirePolicy)
            ->and($updated->timeout)->toBe(5);
    });

    it('принимает resultMetaExtractor и сохраняет в with()', function () {
        $extractor = new class implements ResultMetaExtractorInterface {
            #[\Override]
            public function extract(ExecutionResult $result): ?ResultMeta
            {
                return null;
            }
        };

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            resultMetaExtractor: $extractor,
        );
        $updated = $config->with(timeout: 7);

        expect($config->resultMetaExtractor)->toBe($extractor)
            ->and($updated->resultMetaExtractor)->toBe($extractor)
            ->and($updated->timeout)->toBe(7);
    });

    it('валидирует defaultPollRequest и continuationModeApplicator', function () {
        expect(fn () => new ClientConfig(
            baseUrl: 'https://api.test',
            defaultPollRequest: 'UnknownPollRequest',
        ))->toThrow(
            ConfigurationException::class,
            'ClientConfig.defaultPollRequest class not found: UnknownPollRequest',
        );

        expect(fn () => new ClientConfig(
            baseUrl: 'https://api.test',
            defaultPollRequest: stdClass::class,
        ))->toThrow(
            ConfigurationException::class,
            'ClientConfig.defaultPollRequest must implement RequestInterface',
        );

        $applicator = new class implements ContinuationModeApplicatorInterface {
            #[\Override]
            public function apply(
                RequestInterface $request,
                RequestPartsBag $parts,
                ContinuationMode $mode,
                ?PipelineContext $context = null,
            ): RequestPartsBag {
                return $parts;
            }
        };

        $config = new ClientConfig(
            baseUrl: 'https://api.test',
            defaultContinuationMode: ContinuationMode::Async,
            defaultPollRequest: ContinuationPollRequest::class,
            continuationModeApplicator: $applicator,
        );
        $updated = $config->with(defaultContinuationMode: ContinuationMode::Sync);

        expect($config->defaultPollRequest)->toBe(ContinuationPollRequest::class)
            ->and($config->defaultContinuationMode)->toBe(ContinuationMode::Async)
            ->and($config->continuationModeApplicator)->toBe($applicator)
            ->and($updated->defaultContinuationMode)->toBe(ContinuationMode::Sync)
            ->and($updated->continuationModeApplicator)->toBe($applicator);
    });
});
