<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Hydration;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Serialization\ResponseDecodingException;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydratorInterface;
use ApiSutra\Contracts\Interfaces\Pagination\PaginableInterface;
use ApiSutra\Contracts\Interfaces\Pagination\PaginationItemsContainerInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\Exceptions\ControlFlow\ExecutorContractViolation;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Extensions\ExtensionRegistry;
use ApiSutra\Pagination\PaginationConfigResolver;
use ApiSutra\Pagination\PaginationItemsCollectionBuilder;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\SourceLocation;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Support\ArrayPath;
use ApiSutra\VO\Files\FileResponse;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\DecodedResponse;
use ApiSutra\VO\Pipeline\PipelineContext;
use GuzzleHttp\Psr7\Utils as Psr7Utils;
use Throwable;

/**
 * Гидрация ответа и пагинации.
 * Нюансы:
 * - Для paginated-запросов с #[Returns] возвращается DTO-обёртка.
 * - Items извлекаются по itemsPath и типизируются через itemsType.
 */
final readonly class ResponseHydrator
{
    public function __construct(
        private ClientConfig $config,
        private Hydrator $hydrator,
        private ExtensionRegistry $extensions,
        private ResponseDtoHydratorResolver $dtoHydrators = new ResponseDtoHydratorResolver(),
    ) {
    }

    public function decodeResponse(RequestInterface $request, PipelineContext $context): DecodedResponse
    {
        if ($this->isRawResponse($request, $context)) {
            if ($context->response?->stream !== null) {
                throw new ConfigurationException(new Message('pipeline.rawresponse_does_not_support_streaming_responses'));
            }
            return new DecodedResponse($context->response->body ?? '');
        }
        if ($this->isDownloadRequest($request)) {
            return new DecodedResponse([]);
        }

        $handler = $this->extensions->resolveResponseHandler($context->response, $context);
        if ($handler !== null) {
            // Расширение владеет форматом; строгий JSON применяется только при отказе обработчика.
            return new DecodedResponse($context->response?->json() ?? [], $handler);
        }

        return new DecodedResponse($this->decodeStandardResponse($request, $context->response));
    }

    public function hydrateResponse(
        RequestInterface $request,
        PipelineContext $context,
        mixed $data,
        ?DecodedResponse $decoded = null,
    ): mixed {
        try {
            return $this->hydrateDecodedResponse($request, $context, $data, $decoded);
        } catch (HydrationException $exception) {
            if ($exception->sourcePathKind !== null || !$this->hydrator->tracksSource($this->resolveDtoClass($request))) {
                throw $exception;
            }
            $segments = $exception->path === null || $exception->path === '$' ? [] : explode('.', $exception->path);
            $kind = $exception->reason === 'unwrap_path_missing' ? SourcePathKind::Expected : SourcePathKind::Resolved;
            $pointer = SourceLocation::pointer($segments);
            $source = new SourceLocation(
                $segments,
                $segments,
                $kind,
                $kind === SourcePathKind::Expected ? [$pointer] : [],
                $kind === SourcePathKind::Expected ? [$pointer] : [],
            );
            if ($exception->reason === null) {
                $source = new SourceLocation(kind: SourcePathKind::Unavailable);
            } elseif ($context->hydrationSourceTransformed) {
                $source = (new SourceLocation())->boundary();
            }
            throw $exception->withSource($source);
        }
    }

    private function hydrateDecodedResponse(
        RequestInterface $request,
        PipelineContext $context,
        mixed $data,
        ?DecodedResponse $decoded,
    ): mixed {
        if ($this->isRawResponse($request, $context)) {
            return $data;
        }
        if ($this->isDownloadRequest($request)) {
            return $this->makeFileResponse($context->response);
        }

        $handler = $decoded !== null
            ? $decoded->handler
            : $this->extensions->resolveResponseHandler($context->response, $context);
        $extensionResult = $handler?->handle($context->response, $context);
        if ($extensionResult !== null) {
            return $extensionResult;
        }

        if (($decoded === null && $context->response !== null) || $handler !== null) {
            $standardData = $this->decodeStandardResponse($request, $context->response);
            if (!is_array($standardData)) {
                $data = $standardData;
            }
        }

        $dtoClass = $this->resolveDtoClass($request);
        $returns = $this->resolveReturnsAttribute($request);
        $hydrator = $this->dtoHydrators->resolve($request, $this->config);

        if ($request instanceof AbstractRequest && $request instanceof PaginableInterface) {
            if (!is_array($data)) {
                throw new HydrationException(new Message('pipeline.pagination_response_must_contain_a_json_array_or_object'));
            }
            $pagination = $this->resolvePaginationConfig($request);

            // Режим контейнера: #[Returns] сохраняет структуру ответа, items подставляются отдельно
            if ($returns !== null) {
                $items = $this->extractPaginationItems($data, $pagination);
                $items = $this->hydratePaginationItems($items, $pagination, $context, $hydrator);
                $items = $this->buildItemsCollection($items, $pagination);

                [$data, $dtoClass] = $this->applyUnwrap($data, $returns, $dtoClass);
                $container = $this->hydrateIfNeeded($data, $dtoClass, $context, $hydrator);

                if (!$container instanceof PaginationItemsContainerInterface) {
                    throw new ConfigurationException(new Message('pipeline.pagination_dto_container_must_implement_paginationitemscontainerinterface'));
                }

                return $container->withItems($items);
            }

            if ($this->config->hydration !== null) {
                $items = $this->extractPaginationItems($data, $pagination);
                return $this->buildItemsCollection($this->hydratePaginationItems($items, $pagination, $context, $hydrator), $pagination);
            }

            // Режим items-only: возвращаем только items
            $data = $this->applyPaginationItemsOnly($data, $pagination);
        }

        [$data, $dtoClass] = $this->applyUnwrap($data, $returns, $dtoClass);

        return $this->hydrateIfNeeded($data, $dtoClass, $context, $hydrator);
    }

    private function makeFileResponse(?ProviderResponse $response): FileResponse
    {
        if ($response === null) {
            throw new ConfigurationException(new Message('pipeline.no_response_available_for_file_download'));
        }

        $stream = $response->stream ?? Psr7Utils::streamFor($response->body);
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        $contentType = $response->header('Content-Type');
        $contentDisposition = $response->header('Content-Disposition');

        $filename = null;
        if (is_string($contentDisposition) && preg_match('/filename="?([^"]+)"?/i', $contentDisposition, $matches)) {
            $filename = $matches[1] ?? null;
        }

        return new FileResponse(
            stream: $stream,
            filename: $filename,
            mimeType: $contentType,
            size: $stream->getSize(),
            config: $this->config,
        );
    }

    private function isDownloadRequest(RequestInterface $request): bool
    {
        return $request instanceof AbstractRequest && $request->hasDownload();
    }

    public function assertResponseModeSupported(RequestInterface $request, PipelineContext $context): void
    {
        if (
            $this->isRawResponse($request, $context) && (
            $request->getResponseType() !== null
            || $request instanceof PaginableInterface
            || $this->isDownloadRequest($request)
            || $context->options?->getDownloadTarget() !== null
            || ($request instanceof AbstractRequest && $request->getReturnsAttribute() !== null)
            )
        ) {
            throw new ConfigurationException(new Message('pipeline.rawresponse_is_incompatible_with_dto_pagination_and_download'));
        }
    }

    private function isRawResponse(RequestInterface $request, PipelineContext $context): bool
    {
        return $context->options?->getRawResponseOverride()
            ?? ($request instanceof AbstractRequest && $request->hasRawResponse());
    }

    private function decodeStandardResponse(RequestInterface $request, ?ProviderResponse $response): mixed
    {
        $requiresArray = $this->resolveDtoClass($request) !== null || $request instanceof PaginableInterface;
        if ($response === null || $response->status === 204 || $response->body === '') {
            return $requiresArray ? [] : null;
        }

        $contentType = strtolower(trim(explode(';', $response->header('Content-Type') ?? '')[0]));
        if ($contentType === '' || $contentType === 'application/json' || str_ends_with($contentType, '+json')) {
            $data = $response->jsonStrict();
            return $requiresArray && $data === null ? [] : $data;
        }

        if (!$requiresArray) {
            return $response->body;
        }

        throw new ResponseDecodingException(
            new Message('pipeline.response_format_does_not_support_dto_hydration_or_pagination'),
            reason: 'unsupported_response_content_type',
        );
    }

    private function resolveDtoClass(RequestInterface $request): ?string
    {
        return $request instanceof AbstractRequest ? $request->getResponseType() : null;
    }

    private function resolveReturnsAttribute(RequestInterface $request): ?object
    {
        return $request instanceof AbstractRequest ? $request->getReturnsAttribute() : null;
    }

    private function applyPaginationItemsOnly(mixed $data, PaginationConfig $pagination): mixed
    {
        if ($pagination->itemsPath === '') {
            return $data;
        }

        $items = ArrayPath::getByPath($data, $pagination->itemsPath);
        return $items !== null ? $items : $data;
    }

    /**
     * @param object|null $returns
     * @return array{0: mixed, 1: ?string}
     */
    private function applyUnwrap(mixed $data, ?object $returns, ?string $dtoClass): array
    {
        if ($returns === null || $returns->unwrap === null) {
            return [$data, $dtoClass];
        }

        $unwrapped = ArrayPath::getByPathWithStatus($data, $returns->unwrap);
        if ($unwrapped->isMissing()) {
            throw HydrationException::invalidValue('unwrap_path_missing', 'present', 'missing', $returns->unwrap);
        }

        $dtoClass = $returns->type ?? $dtoClass;

        return [$unwrapped->value, $dtoClass];
    }

    private function hydrateIfNeeded(mixed $data, ?string $dtoClass, PipelineContext $context, DtoHydratorInterface|false|null $hydrator): mixed
    {
        if ($dtoClass === null) {
            return $data;
        }

        $path = $this->resolveReturnsAttribute($context->request)?->unwrap;
        if (!is_array($data) && !is_object($data)) {
            throw HydrationException::invalidValue('unexpected_response_shape', $dtoClass, get_debug_type($data), $path ?? '$');
        }

        try {
            return $this->hydrator->hydrate($data, $dtoClass, $context, $hydrator);
        } catch (HydrationException $exception) {
            if ($exception->reason === 'custom_hydrator_type_mismatch' && $exception->path === '') {
                throw ResponseContractGuard::mismatch($context->request, $context->config, $dtoClass, $exception->actual ?? 'object');
            }
            if ($path !== null) {
                if (!$context->hydrationSourceTransformed) {
                    $exception = $exception->prependSourcePath($path);
                }
                $exception = $exception->prependPath($path);
            }
            throw $exception;
        } catch (SdkException | ExecutorContractViolation $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new HydrationException(new Message('pipeline.failed_to_hydrate_the_response_into', ['dtoClass' => $dtoClass]), 0, $exception);
        }
    }

    private function resolvePaginationConfig(AbstractRequest $request): PaginationConfig
    {
        $resolver = new PaginationConfigResolver($this->config);
        return $resolver->resolve($request);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function extractPaginationItems(mixed $data, PaginationConfig $pagination): array
    {
        if (!is_array($data)) {
            return [];
        }

        if ($pagination->itemsPath === '') {
            return $data;
        }

        $items = ArrayPath::getByPath($data, $pagination->itemsPath);
        return is_array($items) ? $items : [];
    }

    /**
     * @param array<array-key, mixed> $items
     * @return array<array-key, mixed>
     */
    private function hydratePaginationItems(array $items, PaginationConfig $pagination, PipelineContext $context, DtoHydratorInterface|false|null $hydrator): array
    {
        if ($pagination->itemsType === null) {
            return $items;
        }

        try {
            return $this->hydrator->hydrateCollection($items, $pagination->itemsType, $context, $hydrator);
        } catch (HydrationException $exception) {
            if ($pagination->itemsPath !== '' && !$context->hydrationSourceTransformed) {
                $exception = $exception->prependSourcePath($pagination->itemsPath);
            }
            throw $exception->prependPath($pagination->itemsPath === '' ? '$' : $pagination->itemsPath);
        } catch (SdkException | ExecutorContractViolation $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new HydrationException(new Message('pipeline.failed_to_hydrate_response_items_into', ['value0' => $pagination->itemsType]), 0, $exception);
        }
    }

    /**
     * @param array<array-key, mixed> $items
     */
    private function buildItemsCollection(array $items, PaginationConfig $pagination): array|object
    {
        $builder = new PaginationItemsCollectionBuilder();
        return $builder->build($items, $pagination);
    }
}
