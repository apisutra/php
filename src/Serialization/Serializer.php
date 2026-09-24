<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Contracts\Interfaces\Localization\LocalizableExceptionInterface;
use ApiSutra\Localization\Message;
use ApiSutra\Attributes\Behavior\SkipContinuation;
use ApiSutra\Diagnostics\SensitiveFields;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Metadata\MetadataCatalog;
use Psr\Http\Message\StreamInterface;
use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Contracts\Interfaces\Continuation\ContinuationModeApplicatorInterface;
use ApiSutra\Contracts\Interfaces\Serialization\RequestPartsEnricherInterface;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Pagination\PaginableInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\Serialization\BooleanFormat;
use ApiSutra\Request\RequestPaginationHelper;
use ApiSutra\Http\RequestDestination;
use ApiSutra\Files\DownloadManager;
use ApiSutra\VO\Files\FileTransferOptions;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Serialization\Enrichment\CredentialsEnricher;
use ApiSutra\Serialization\VO\RequestPartsBag;
use ApiSutra\Serialization\Rules\ReceiverOutput;
use ApiSutra\Serialization\Rules\RuleSetCompiler;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

/**
 * Центральный orchestrator сериализации RequestInterface -> PreparedRequest.
 *
 * Порядок этапов (инвариант):
 * 1) collect частей запроса (query/body/headers/files/placeholders);
 * 2) request enrichers (credentials и кастомные enrichers);
 * 3) continuation mode applicator (provider-специфичный mapping mode -> protocol fields);
 * 4) сборка URL и finalize payload.
 *
 * Важно:
 * - Serializer не знает provider-протокол напрямую;
 * - mode-resolve для continuation: runtime override -> ContinuationResult.defaultMode -> ClientConfig.defaultContinuationMode.
 *
 * @see docs/reference/serialization/README.md
 * @see docs/reference/execution/continuation.md
 * @see docs/development/pipeline.md
 */
final class Serializer
{
    private ?DtoSerializer $dtoSerializer = null;
    private ?RequestPartsCollector $partsCollector = null;
    private ?RuleSetCompiler $descriptions = null;
    private readonly RequestUrlBuilder $urlBuilder;
    private readonly FilePayloadPreparer $filePayloadPreparer;
    private readonly DtoSerializationProfileResolver $profileResolver;

    public function __construct(
        private readonly CastRegistry $casts,
        private readonly ?AttributeMetadataCache $cache = null,
        private readonly ?HydrationConfig $config = null,
        private readonly LocalizationConfig $localization = new LocalizationConfig(),
    ) {
        try {
            $this->profileResolver = new DtoSerializationProfileResolver();
            // Явная конфигурация проверяется сразу; internal-сборка передаст готовый compiler.
            if ($config !== null) {
                $this->descriptions = new RuleSetCompiler($config, $cache?->catalog() ?? new MetadataCatalog());
            }
            $this->urlBuilder = new RequestUrlBuilder();
            $this->filePayloadPreparer = new FilePayloadPreparer();
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->localization);
        }
    }

    /** @internal Собирает wire-путь с тем же описанием, что использует гидратор клиента. */
    public static function withDescriptions(
        CastRegistry $casts,
        ?AttributeMetadataCache $cache,
        RuleSetCompiler $descriptions,
        LocalizationConfig $localization = new LocalizationConfig(),
    ): self {
        $serializer = new self($casts, $cache, localization: $localization);
        $serializer->descriptions = $descriptions;
        return $serializer;
    }

    private function descriptions(): RuleSetCompiler
    {
        return $this->descriptions ??= new RuleSetCompiler(
            metadata: $this->cache?->catalog() ?? new MetadataCatalog(),
        );
    }

    private function partsCollector(): RequestPartsCollector
    {
        return $this->partsCollector ??= new RequestPartsCollector(
            casts: $this->casts,
            cache: $this->cache,
            dtoSerializer: fn (object $dto, ?PipelineContext $context): array => $this->serializeWireDto($dto, $context),
            receiverOutput: new ReceiverOutput($this->descriptions()),
            catalog: $this->descriptions()->catalog(),
        );
    }

    public function serialize(RequestInterface $request, ?PipelineContext $context = null): PreparedRequest
    {
        try {
            $method = $request->getMethod();
            $endpoint = $request->getEndpoint();
            $baseUrl = $this->resolveBaseUrl($request, $context);
            $options = $context->options ?? ($request instanceof AbstractRequest ? $request->getOptions() : null);
            $fullUrl = $options?->getUrlOverride();
            if ($fullUrl === null && RequestDestination::isAbsolute($endpoint)) {
                $fullUrl = $endpoint;
            }
            if ($fullUrl !== null) {
                $fullUrl = explode('#', $fullUrl, 2)[0];
                RequestDestination::validateFullUrl($fullUrl);
            }
            $destination = $baseUrl !== '' || $fullUrl !== null
                ? new RequestDestination($fullUrl ?? $baseUrl, $context?->config->baseUrl ?: ($fullUrl ?? $baseUrl), $fullUrl !== null)
                : null;
            if ($context !== null) {
                $context->destination = $destination;
            }
            $paginationOverrides = $this->resolvePaginationOverrides($request, $context);
            $placeholders = $fullUrl !== null ? [] : $this->urlBuilder->extractPathParams($endpoint);
            $parts = $this->buildParts(
                request: $request,
                context: $context,
                paginationOverrides: $paginationOverrides,
                placeholders: $placeholders,
                method: $method,
            );

            $parts = $this->applyRequestEnrichers($request, $parts, $context);
            $continuationMode = null;
            [$parts, $continuationMode] = $this->applyContinuationModeApplicator($request, $parts, $context);

            if ($fullUrl !== null) {
                foreach ($parts->query as $query) {
                    if (($query['value'] ?? null) !== []) {
                        throw new ConfigurationException(new Message('serialization.absolute_url_is_incompatible_with_additional_query_parameters'));
                    }
                }
            }
            $url = $fullUrl ?? $this->buildPreparedUrl(
                baseUrl: $baseUrl,
                endpoint: $endpoint,
                parts: $parts,
                context: $context,
            );

            $prepared = $this->preparePayload($parts, $context);

            $download = $request instanceof AbstractRequest && $request->hasDownload();
            $target = $options?->getDownloadTarget();
            if ($target !== null && !$download) {
                throw new ConfigurationException(new Message('serialization.withdownloadto_requires_download'));
            }
            DownloadManager::validate($target);
            $transfer = $download || $prepared['stream'] !== null || $parts->files !== []
                ? new FileTransferOptions($prepared['stream'] !== null, $download, $target)
                : null;
            if ($context !== null) {
                $context->fileTransfer = $transfer;
            }
            return new PreparedRequest(
                fileTransfer: $transfer,
                method: $method,
                url: $url,
                headers: $prepared['headers'],
                destination: $destination,
                body: $prepared['body'],
                stream: $prepared['stream'],
                meta: [
                    'query' => $parts->query,
                    'body' => $parts->body,
                    'bodyIsRoot' => $parts->bodyIsRoot,
                    'files' => $parts->files,
                    'requestClass' => $request::class,
                    'requestInstance' => $request,
                    'oneOf' => $context?->requestContractDebug,
                    'credentialsEnrichment' => $parts->enrichment['credentials'] ?? null,
                    'sensitiveFields' => SensitiveFields::declared($request),
                    'continuationMode' => $continuationMode?->value,
                ],
            );
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($context?->config->localization ?? $this->localization);
        }
    }

    private function resolveBaseUrl(RequestInterface $request, ?PipelineContext $context): string
    {
        $baseUrl = $context?->config->baseUrl ?? '';

        if ($context?->options?->getBaseUrlOverride() !== null) {
            return $context->options->getBaseUrlOverride();
        }

        if ($request instanceof AbstractRequest) {
            return $request->getBaseUrl() ?? $baseUrl;
        }

        return $baseUrl;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolvePaginationOverrides(RequestInterface $request, ?PipelineContext $context): array
    {
        if (
            $context?->paginationOptions === null
            || !$request instanceof AbstractRequest
            || !$request instanceof PaginableInterface
        ) {
            return [];
        }

        return new RequestPaginationHelper($context, null)
            ->resolvePaginationOverrides($request, $context->paginationOptions);
    }

    /**
     * @param array<string, mixed> $paginationOverrides
     * @param array<string, mixed> $placeholders
     */
    private function buildParts(
        RequestInterface $request,
        ?PipelineContext $context,
        array $paginationOverrides,
        array $placeholders,
        HttpMethod $method,
    ): RequestPartsBag {
        return $this->partsCollector()->collect(
            request: $request,
            context: $context,
            paginationOverrides: $paginationOverrides,
            placeholders: $placeholders,
            method: $method,
        );
    }

    /**
     * Сборка итогового URL после обогащения parts.
     */
    private function buildPreparedUrl(
        string $baseUrl,
        string $endpoint,
        RequestPartsBag $parts,
        ?PipelineContext $context,
    ): string {
        return $this->urlBuilder->buildUrl(
            baseUrl: $baseUrl,
            endpoint: $endpoint,
            placeholders: $parts->placeholders,
            query: $parts->query,
            context: $context,
        );
    }

    /**
     * @return array{body: ?string, stream: ?StreamInterface, headers: array<string, string>}
     */
    private function preparePayload(RequestPartsBag $parts, ?PipelineContext $context): array
    {
        return $this->filePayloadPreparer->prepareBodyAndStream(
            fileFormat: $parts->fileFormat,
            files: $parts->files,
            body: $parts->body,
            bodyIsRoot: $parts->bodyIsRoot,
            headers: $parts->headers,
            booleanFormat: $context?->config->textBooleanFormat ?? BooleanFormat::Numeric,
        );
    }

    private function applyRequestEnrichers(
        RequestInterface $request,
        RequestPartsBag $parts,
        ?PipelineContext $context,
    ): RequestPartsBag {
        foreach ($this->resolveRequestEnrichers($context) as $enricher) {
            $parts = $enricher->enrich($request, $parts, $context);
        }

        return $parts;
    }

    /**
     * @return array{0: RequestPartsBag, 1: ?ContinuationMode}
     */
    private function applyContinuationModeApplicator(
        RequestInterface $request,
        RequestPartsBag $parts,
        ?PipelineContext $context,
    ): array {
        $applicator = $this->resolveContinuationModeApplicator($context);
        if ($applicator === null || $this->descriptions()->catalog()->forClass($request::class)->attributes(SkipContinuation::class) !== []) {
            return [$parts, null];
        }

        $mode = $this->resolveContinuationMode($request, $context);

        return [$applicator->apply($request, $parts, $mode, $context), $mode];
    }

    private function resolveContinuationMode(RequestInterface $request, ?PipelineContext $context): ContinuationMode
    {
        $modeOverride = $context?->options?->getContinuationModeOverride();
        if ($modeOverride === null && $request instanceof AbstractRequest) {
            $modeOverride = $request->getContinuationModeOverride();
        }
        if ($modeOverride !== null) {
            return $modeOverride;
        }

        if ($request instanceof AbstractRequest) {
            $defaultMode = $request->getContinuationResultAttribute()?->defaultMode;
            if ($defaultMode !== null) {
                return $defaultMode;
            }
        }

        return $context?->config->defaultContinuationMode ?? ContinuationMode::Auto;
    }

    private function resolveContinuationModeApplicator(?PipelineContext $context): ?ContinuationModeApplicatorInterface
    {
        $applicator = $context?->config->continuationModeApplicator;

        return $applicator instanceof ContinuationModeApplicatorInterface ? $applicator : null;
    }

    /**
     * @return array<int, RequestPartsEnricherInterface>
     */
    private function resolveRequestEnrichers(?PipelineContext $context): array
    {
        $config = $context?->config;
        if ($config === null) {
            return [];
        }

        $isolated = $context?->destination?->requiresIsolation() ?? false;
        $credentials = $context->options?->getCredentialsEnrichmentEnabledOverride();
        $custom = $context->options?->getRequestEnrichersEnabledOverride();
        if ($isolated && ($credentials === true || $custom === true)) {
            $context->destination->assertCredentialsAllowed($config->originPolicy);
        }
        $enrichers = [];
        if ($config->credentialsConfig !== null && (!$isolated || $credentials === true)) {
            $enrichers[] = new CredentialsEnricher($config->credentialsConfig);
        }

        if ($custom === false || ($isolated && $custom !== true)) {
            return $enrichers;
        }
        foreach ($config->requestEnrichers as $enricher) {
            if ($enricher instanceof RequestPartsEnricherInterface) {
                $enrichers[] = $enricher;
            }
        }

        return $enrichers;
    }

    private function getDtoSerializer(): DtoSerializer
    {
        if ($this->dtoSerializer === null) {
            $this->dtoSerializer = DtoSerializer::withDescriptions($this->casts, $this->cache, $this->descriptions());
        }

        return $this->dtoSerializer;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeWireDto(object $dto, ?PipelineContext $context): array
    {
        $this->descriptions()->receiverFor($dto::class);
        $resolved = $this->profileResolver->resolveForWireDto($dto::class, $context?->config);

        return $this->getDtoSerializer()->serializeWithPolicy(
            dto: $dto,
            policy: $resolved->policy,
            context: $context,
            casts: $resolved->casts,
        );
    }
}
