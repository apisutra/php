<?php

declare(strict_types=1);

namespace ApiSutra\Config;

use ApiSutra\RateLimiting\Cooldown\CooldownBackendInterface;
use ApiSutra\Contracts\Interfaces\Localization\LocalizableExceptionInterface;
use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Continuation\ContinuationStateResolverInterface;
use ApiSutra\RateLimiting\RateLimitBackendInterface;
use ApiSutra\Diagnostics\RedactionPolicy;
use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Auth\AuthPolicyInterface;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Contracts\Interfaces\Casting\SerializationCastInterface;
use ApiSutra\Contracts\Interfaces\Catalog\ProviderCatalogRegistryInterface;
use ApiSutra\Contracts\Interfaces\Continuation\ContinuationModeApplicatorInterface;
use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Extensions\ExtensionInterface;
use ApiSutra\Contracts\Interfaces\Serialization\DtoSerializationProfileInterface;
use ApiSutra\Contracts\Interfaces\Serialization\RequestPartsEnricherInterface;
use ApiSutra\Enums\Configuration\Environment;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Enums\Http\QueryArrayFormat;
use ApiSutra\Enums\Serialization\EnumOutput;
use ApiSutra\Enums\Serialization\BooleanFormat;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Result\ContinuationTokenExtractorInterface;
use ApiSutra\Response\ClientResponseFactoryInterface;
use ApiSutra\Result\ResolvedResultFactoryInterface;
use ApiSutra\Result\ResultMetaExtractorInterface;
use ApiSutra\VO\Errors\ClientErrorMapperInterface;
use ApiSutra\VO\Errors\ErrorContextFactoryInterface;
use DateTimeZone;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Throwable;

/**
 * Единая immutable-конфигурация клиента ApiSutra.
 *
 * Назначение:
 * - задаёт глобальные дефолты поведения пайплайна;
 * - передаёт расширения и стратегии (auth/error/continuation и т.д.);
 * - служит нижним уровнем приоритета для request-атрибутов и runtime-overrides.
 *
 * Нюансы continuation-блока:
 * - continuationTokenExtractor: единая стратегия извлечения token из ExecutionResult;
 * - resultMetaExtractor: единая стратегия извлечения provider envelope meta из ExecutionResult;
 * - providerCatalogRegistry: read-only статические provider catalogs (DX metadata), не связанные с runtime result meta;
 * - defaultContinuationMode: дефолт mode для optional async;
 * - defaultPollRequest: poll-request fallback для awaitByToken/await;
 * - continuationModeApplicator: provider-specific mapping mode в transport-поля;
 * - continuationStateResolver: явное определение Pending/Ready/Failed для ожидания.
 *
 * @see docs/reference/client/configuration.md
 * @see docs/reference/results/errors.md
 * @see docs/guides/recipes/continuation.md
 */
final readonly class ClientConfig
{
    public PaginationRule $paginationRule;
    public DateTimeSerializationPolicy $requestDateTime;
    public LocalizationConfig $localization;

    /**
     * @param array<string, HydrationCastInterface|SerializationCastInterface|class-string<HydrationCastInterface|SerializationCastInterface>> $casts Касты по типам.
     * @param array<ExtensionInterface> $extensions Расширения клиента.
     * @param array<RequestPartsEnricherInterface> $requestEnrichers Enrichers request parts до finalize payload.
     * @param ContinuationTokenExtractorInterface|null $continuationTokenExtractor Извлечение continuation token из результата.
     * @param ResultMetaExtractorInterface|null $resultMetaExtractor Извлечение provider meta из результата.
     * @param ProviderCatalogRegistryInterface|null $providerCatalogRegistry Read-only provider catalogs SDK.
     * @param string|null $defaultPollRequest Класс poll-request по умолчанию для awaitByToken/await.
     * @param array<string, AuthenticatorInterface> $authScopes Ключи рекомендуется задавать через enum.
     */
    public function __construct(
        public string $baseUrl,
        public ?AuthenticatorInterface $auth = null,
        public array $authScopes = [],
        public ?AuthPolicyInterface $authPolicy = null,
        public bool $authRetryOn401 = true,
        public int $authRetryAttempts = 1,
        public ?LoggerInterface $logger = null,
        public string $logLevel = LogLevel::INFO,
        public ?CacheConfig $cacheConfig = null,
        public int $timeout = 30,
        public int $connectTimeout = 10,
        public ?RetryConfig $retry = null,
        public ?RateLimitConfig $rateLimit = null,
        public ?PoolConfig $pool = null,
        public QueryArrayFormat $queryArrayFormat = QueryArrayFormat::Brackets,
        public bool $serializeNulls = false,
        public NamingStrategy $namingStrategy = NamingStrategy::None,
        public array $casts = [],
        public ?DtoSerializationProfileInterface $dtoSerializationProfile = null,
        public ?DtoSerializationPolicy $wireBodySerializationPolicy = null,
        public EnumOutput $requestPartsEnumOutput = EnumOutput::Value,
        public bool $requestPartsStrictEnums = false,
        ?DateTimeSerializationPolicy $requestDateTime = null,
        public int $delay = 0,
        public bool $throwOnErrors = false,
        public bool $debug = false,
        public Environment $environment = Environment::Production,
        public string $idempotencyHeader = 'Idempotency-Key',
        public array $extensions = [],
        public ?PaginationConfig $paginationConfig = null,
        public ?ArchiveConfig $archive = null,
        ?PaginationRule $paginationRule = null,
        public ?ResolvedResultFactoryInterface $resolvedResultFactory = null,
        public ?ClientErrorMapperInterface $errorMapper = null,
        public ?ErrorContextFactoryInterface $errorContextFactory = null,
        public ?ClientResponseFactoryInterface $responseFactory = null,
        public ?ContainerProviderInterface $containerProvider = null,
        public array $requestEnrichers = [],
        public ?CredentialsEnrichmentConfig $credentialsConfig = null,
        public ?ContinuationTokenExtractorInterface $continuationTokenExtractor = null,
        public ?ResultMetaExtractorInterface $resultMetaExtractor = null,
        public ?ProviderCatalogRegistryInterface $providerCatalogRegistry = null,
        public ContinuationMode $defaultContinuationMode = ContinuationMode::Auto,
        public ?string $defaultPollRequest = null,
        public ?ContinuationModeApplicatorInterface $continuationModeApplicator = null,
        public RedactionPolicy $redaction = new RedactionPolicy(),
        public BooleanFormat $textBooleanFormat = BooleanFormat::Numeric,
        public OriginPolicy $originPolicy = new OriginPolicy(),
        public bool $includeClientQuota = true,
        public ?RateLimitBackendInterface $rateLimitBackend = null,
        public ?ContinuationStateResolverInterface $continuationStateResolver = null,
        public ?HydrationConfig $hydration = null,
        public ?ResultExceptionConfig $resultExceptions = null,
        LocalizationConfig|string $localization = new LocalizationConfig(),
        public CooldownConfig $cooldown = new CooldownConfig(),
        public ?CooldownBackendInterface $cooldownBackend = null,
        public ?string $diagnosticLabel = null,
    ) {
        $this->localization = is_string($localization) ? new LocalizationConfig($localization) : $localization;
        try {
            $this->paginationRule = $paginationRule ?? PaginationRule::single();
            $this->requestDateTime = $requestDateTime ?? new DateTimeSerializationPolicy();
            $this->validate();
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->localization);
        }
    }

    /**
     * Создать с переопределениями
     */
    public function with(mixed ...$overrides): self
    {
        $data = [
            'baseUrl' => $this->baseUrl,
            'auth' => $this->auth,
            'authScopes' => $this->authScopes,
            'authPolicy' => $this->authPolicy,
            'authRetryOn401' => $this->authRetryOn401,
            'authRetryAttempts' => $this->authRetryAttempts,
            'logger' => $this->logger,
            'logLevel' => $this->logLevel,
            'cacheConfig' => $this->cacheConfig,
            'timeout' => $this->timeout,
            'connectTimeout' => $this->connectTimeout,
            'retry' => $this->retry,
            'rateLimit' => $this->rateLimit,
            'cooldown' => $this->cooldown,
            'cooldownBackend' => $this->cooldownBackend,
            'diagnosticLabel' => $this->diagnosticLabel,
            'includeClientQuota' => $this->includeClientQuota,
            'rateLimitBackend' => $this->rateLimitBackend,
            'pool' => $this->pool,
            'queryArrayFormat' => $this->queryArrayFormat,
            'serializeNulls' => $this->serializeNulls,
            'namingStrategy' => $this->namingStrategy,
            'casts' => $this->casts,
            'dtoSerializationProfile' => $this->dtoSerializationProfile,
            'wireBodySerializationPolicy' => $this->wireBodySerializationPolicy,
            'requestPartsEnumOutput' => $this->requestPartsEnumOutput,
            'requestPartsStrictEnums' => $this->requestPartsStrictEnums,
            'requestDateTime' => $this->requestDateTime,
            'delay' => $this->delay,
            'throwOnErrors' => $this->throwOnErrors,
            'debug' => $this->debug,
            'environment' => $this->environment,
            'idempotencyHeader' => $this->idempotencyHeader,
            'extensions' => $this->extensions,
            'paginationConfig' => $this->paginationConfig,
            'archive' => $this->archive,
            'paginationRule' => $this->paginationRule,
            'resolvedResultFactory' => $this->resolvedResultFactory,
            'errorMapper' => $this->errorMapper,
            'errorContextFactory' => $this->errorContextFactory,
            'responseFactory' => $this->responseFactory,
            'containerProvider' => $this->containerProvider,
            'requestEnrichers' => $this->requestEnrichers,
            'credentialsConfig' => $this->credentialsConfig,
            'continuationTokenExtractor' => $this->continuationTokenExtractor,
            'resultMetaExtractor' => $this->resultMetaExtractor,
            'providerCatalogRegistry' => $this->providerCatalogRegistry,
            'defaultContinuationMode' => $this->defaultContinuationMode,
            'defaultPollRequest' => $this->defaultPollRequest,
            'continuationModeApplicator' => $this->continuationModeApplicator,
            'continuationStateResolver' => $this->continuationStateResolver,
            'hydration' => $this->hydration,
            'resultExceptions' => $this->resultExceptions,
            'localization' => $this->localization,
            'redaction' => $this->redaction,
            'textBooleanFormat' => $this->textBooleanFormat,
            'originPolicy' => $this->originPolicy,
        ];

        foreach ($overrides as $key => $value) {
            $data[$key] = $value;
        }

        return new self(...$data);
    }

    public function getPaginationRule(): PaginationRule
    {
        return $this->paginationRule;
    }

    /**
     * Валидировать параметры клиента.
     */
    private function validate(): void
    {
        if ($this->rateLimitBackend !== null && $this->rateLimit?->store !== null) {
            throw new ConfigurationException(
                new Message('configuration.shared_ratelimit_store_is_incompatible_with_ratelimitbackend_use_one'),
            );
        }
        if (trim($this->baseUrl) === '') {
            throw new ConfigurationException(new Message('configuration.clientconfig_baseurl_must_not_be_empty'));
        }

        if ($this->timeout < 0) {
            throw new ConfigurationException(new Message('configuration.clientconfig_timeout_must_be_0'));
        }

        if ($this->connectTimeout < 0) {
            throw new ConfigurationException(new Message('configuration.clientconfig_connecttimeout_must_be_0'));
        }

        if ($this->delay < 0) {
            throw new ConfigurationException(new Message('configuration.clientconfig_delay_must_be_0'));
        }

        if ($this->authRetryAttempts < 0) {
            throw new ConfigurationException(new Message('configuration.clientconfig_authretryattempts_must_be_0'));
        }

        if (trim($this->idempotencyHeader) === '') {
            throw new ConfigurationException(new Message('configuration.clientconfig_idempotencyheader_must_not_be_empty'));
        }

        foreach ($this->casts as $type => $cast) {
            if ($cast instanceof HydrationCastInterface || $cast instanceof SerializationCastInterface) {
                continue;
            }

            if (is_string($cast)) {
                if (!class_exists($cast)) {
                    throw new ConfigurationException(new Message('configuration.clientconfig_casts_class_not_found', ['type' => $type, 'cast' => $cast]));
                }

                if (!is_subclass_of($cast, HydrationCastInterface::class) && !is_subclass_of($cast, SerializationCastInterface::class)) {
                    throw new ConfigurationException(new Message('configuration.clientconfig_casts_must_implement_hydrationcastinterface_or_serializationcastinterface', ['type' => $type, 'cast' => $cast]));
                }

                continue;
            }

            throw new ConfigurationException(new Message('configuration.clientconfig_casts_contains_an_invalid_value', ['type' => $type]));
        }

        foreach ($this->requestEnrichers as $index => $enricher) {
            if (!$enricher instanceof RequestPartsEnricherInterface) {
                throw new ConfigurationException(
                    new Message('configuration.clientconfig_requestenrichers_must_implement_requestpartsenricherinterface', ['index' => $index]),
                );
            }
        }

        if ($this->credentialsConfig !== null) {
            foreach ($this->credentialsConfig->scopes as $scope => $config) {
                if (!$config instanceof CredentialsScopeConfig) {
                    throw new ConfigurationException(
                        new Message('configuration.clientconfig_credentialsconfig_scopes_must_be_credentialsscopeconfig', ['scope' => $scope]),
                    );
                }
            }
        }

        if ($this->defaultPollRequest !== null) {
            $pollRequestClass = trim($this->defaultPollRequest);
            if ($pollRequestClass === '') {
                throw new ConfigurationException(new Message('configuration.clientconfig_defaultpollrequest_must_not_be_empty'));
            }

            if (!class_exists($pollRequestClass)) {
                throw new ConfigurationException(new Message('configuration.clientconfig_defaultpollrequest_class_not_found', ['pollRequestClass' => $pollRequestClass]));
            }

            if (!is_subclass_of($pollRequestClass, RequestInterface::class)) {
                throw new ConfigurationException(
                    new Message('configuration.clientconfig_defaultpollrequest_must_implement_requestinterface', ['pollRequestClass' => $pollRequestClass]),
                );
            }
        }

        if ($this->requestPartsEnumOutput === EnumOutput::Object) {
            throw new ConfigurationException(new Message('configuration.clientconfig_requestpartsenumoutput_cannot_be_object_for_query_header_path'));
        }

        $this->validateTimezone($this->requestDateTime->timezone, 'ClientConfig.requestDateTime.timezone');
    }

    private function validateTimezone(?string $timezone, string $path): void
    {
        if ($timezone === null) {
            return;
        }

        try {
            new DateTimeZone($timezone);
        } catch (Throwable) {
            throw new ConfigurationException(new Message('configuration.contains_an_invalid_timezone', ['path' => $path, 'timezone' => $timezone]));
        }
    }
}
