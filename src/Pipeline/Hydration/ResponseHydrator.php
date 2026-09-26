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
use ApiSutra\Serialization\Input\HydrationInput;
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

        $input = $this->decodeStandardResponse($request, $context->response);
        return new DecodedResponse($input->value, shape: $input->shape, jsonSourceKnown: $input->jsonSourceKnown);
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

        $input = new HydrationInput(
            $data,
            $context->hydrationInputReplaced ? null : $decoded?->shape,
            !$context->hydrationInputReplaced && ($decoded->jsonSourceKnown ?? false),
        );
        if (($decoded === null && $context->response !== null) || $handler !== null) {
            $standardData = $this->decodeStandardResponse($request, $context->response);
            if (!is_array($standardData->value)) {
                $data = $standardData->value;
                $input = $standardData;
            } elseif (!$context->hydrationInputReplaced) {
                $input = new HydrationInput($data, $standardData->shape, $standardData->jsonSourceKnown);
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
                $items = $this->extractPaginationItems($input, $pagination);
                $items = $this->hydratePaginationItems($items, $pagination, $context, $hydrator);
                $items = $this->buildItemsCollection($items, $pagination);

                [$input, $dtoClass] = $this->applyUnwrap($input, $returns, $dtoClass);
                $container = $this->hydrateIfNeeded($input, $dtoClass, $context, $hydrator);

                if (!$container instanceof PaginationItemsContainerInterface) {
                    throw new ConfigurationException(new Message('pipeline.pagination_dto_container_must_implement_paginationitemscontainerinterface'));
                }

                return $container->withItems($items);
            }

            if ($this->config->hydration !== null) {
                $items = $this->extractPaginationItems($input, $pagination);
                return $this->buildItemsCollection($this->hydratePaginationItems($items, $pagination, $context, $hydrator), $pagination);
            }

            // Режим items-only: возвращаем только items
            $input = $this->applyPaginationItemsOnly($input, $pagination);
        }

        [$input, $dtoClass] = $this->applyUnwrap($input, $returns, $dtoClass);

        return $this->hydrateIfNeeded($input, $dtoClass, $context, $hydrator);
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

    private function decodeStandardResponse(RequestInterface $request, ?ProviderResponse $response): HydrationInput
    {
        $requiresArray = $this->resolveDtoClass($request) !== null || $request instanceof PaginableInterface;
        if ($response === null || $response->status === 204 || $response->body === '') {
            return new HydrationInput($requiresArray ? [] : null);
        }

        $contentType = strtolower(trim(explode(';', $response->header('Content-Type') ?? '')[0]));
        if ($contentType === '' || $contentType === 'application/json' || str_ends_with($contentType, '+json')) {
            $requiresShapes = $this->resolveDtoClass($request) !== null
                || $this->config->hydration !== null
                    && $request instanceof AbstractRequest && $request instanceof PaginableInterface
                    && $this->resolvePaginationConfig($request)->itemsType !== null;
            $input = $response->jsonInput($requiresShapes && ($this->config->hydration->jsonShapeValidation ?? true));
            return $requiresArray && $input->value === null ? new HydrationInput([]) : $input;
        }

        if (!$requiresArray) {
            return new HydrationInput($response->body);
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

    private function applyPaginationItemsOnly(HydrationInput $data, PaginationConfig $pagination): HydrationInput
    {
        if ($pagination->itemsPath === '') {
            return $data;
        }

        $items = $data->select($pagination->itemsPath);
        return $items->value !== null ? $items : $data;
    }

    /**
     * @param object|null $returns
     * @return array{0: HydrationInput, 1: ?string}
     */
    private function applyUnwrap(HydrationInput $data, ?object $returns, ?string $dtoClass): array
    {
        if ($returns === null || $returns->unwrap === null) {
            return [$data, $dtoClass];
        }

        $unwrapped = ArrayPath::getByPathWithStatus($data->value, $returns->unwrap);
        if ($unwrapped->isMissing()) {
            throw HydrationException::invalidValue('unwrap_path_missing', 'present', 'missing', $returns->unwrap);
        }

        $dtoClass = $returns->type ?? $dtoClass;

        return [$data->select($returns->unwrap), $dtoClass];
    }

    private function hydrateIfNeeded(HydrationInput $input, ?string $dtoClass, PipelineContext $context, DtoHydratorInterface|false|null $hydrator): mixed
    {
        $data = $input->value;
        if ($dtoClass === null) {
            return $data;
        }

        $path = $this->resolveReturnsAttribute($context->request)?->unwrap;
        if (!is_array($data) && !is_object($data)) {
            throw HydrationException::invalidValue('unexpected_response_shape', $dtoClass, get_debug_type($data), $path ?? '$');
        }

        try {
            return $this->hydrator->hydrateInput(
                $input,
                $dtoClass,
                $context,
                $hydrator,
                $this->resolveReturnsAttribute($context->request)->emptyListAsObject ?? false,
            );
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
            if ($exception->path === '') {
                $exception = $exception->prependPath('$');
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

    private function extractPaginationItems(HydrationInput $input, PaginationConfig $pagination): HydrationInput
    {
        $items = $pagination->itemsPath === '' ? $input : $input->select($pagination->itemsPath);
        return is_array($items->value) ? $items : new HydrationInput([]);
    }

    /** @return array<array-key, mixed> */
    private function hydratePaginationItems(HydrationInput $items, PaginationConfig $pagination, PipelineContext $context, DtoHydratorInterface|false|null $hydrator): array
    {
        if ($pagination->itemsType === null) {
            return $items->value;
        }

        try {
            return $this->hydrator->hydrateCollectionInput($items, $pagination->itemsType, $context, $hydrator);
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
