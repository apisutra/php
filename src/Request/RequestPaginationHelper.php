<?php

declare(strict_types=1);

namespace ApiSutra\Request;

use ApiSutra\Localization\Message;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Config\PaginationConfig;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Pagination\PaginableInterface;
use ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaOverrideInterface;
use ApiSutra\Contracts\Interfaces\Pagination\PaginationMetaResolverInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Pagination\DefaultPaginationMetaResolver;
use ApiSutra\Pagination\PaginationConfigResolver;
use ApiSutra\Pagination\Paginator;
use ApiSutra\Support\ArrayPath;
use ApiSutra\VO\Metadata\PaginationMeta;
use ApiSutra\VO\Pipeline\PipelineContext;
use ReflectionProperty;

final readonly class RequestPaginationHelper
{
    public function __construct(
        private ?PipelineContext $context,
        private ?ClientInterface $client,
    ) {
    }

    public function paginate(AbstractRequest $request): Paginator
    {
        if (!$request instanceof PaginableInterface) {
            throw new ConfigurationException(new Message('request.request_does_not_support_pagination'));
        }

        return new Paginator($request);
    }

    public function setPage(AbstractRequest $request, int $page): void
    {
        $param = $this->resolvePaginationParam($request, 'page');
        $this->setPaginationValue($request, $param, $page, true);
    }

    public function setLimit(AbstractRequest $request, int $limit): void
    {
        $param = $this->resolvePaginationParam($request, 'limit');
        $this->setPaginationValue($request, $param, $limit, true);
    }

    public function setCursor(AbstractRequest $request, ?string $cursor): void
    {
        $param = $this->resolvePaginationParam($request, 'cursor');
        if ($param !== null) {
            $this->setPaginationValue($request, $param, $cursor, false);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function resolvePaginationOverrides(AbstractRequest $request, PaginationOptions $options): array
    {
        $overrides = [];
        if ($options->hasPage()) {
            $param = $this->resolvePaginationParam($request, 'page');
            if ($param !== null) {
                $overrides[$param] = $options->getPage();
            }
        }

        if ($options->hasLimit()) {
            $param = $this->resolvePaginationParam($request, 'limit');
            if ($param !== null) {
                $overrides[$param] = $options->getLimit();
            }
        }

        if ($options->hasCursor()) {
            $param = $this->resolvePaginationParam($request, 'cursor');
            if ($param !== null) {
                $overrides[$param] = $options->getCursor();
            }
        }

        return $overrides;
    }

    public function resolvePaginationConfig(AbstractRequest $request): PaginationConfig
    {
        $resolver = new PaginationConfigResolver($this->resolveClientConfig());
        return $resolver->resolve($request);
    }

    public function extractMeta(AbstractRequest $request, array $response): PaginationMeta
    {
        $config = $this->resolvePaginationConfig($request);
        $meta = $this->resolveMetaArray($response, $config);

        if ($request instanceof PaginationMetaOverrideInterface) {
            return $request->resolvePaginationMeta($response, $meta, $config, $this->context);
        }

        $resolver = $this->resolveMetaResolver($config);
        if ($resolver !== null) {
            return $resolver->resolve($request, $response, $meta, $config, $this->context);
        }

        return (new DefaultPaginationMetaResolver())->resolve($request, $response, $meta, $config, $this->context);
    }

    private function resolvePaginationParam(AbstractRequest $request, string $type): ?string
    {
        $config = $this->resolvePaginationConfig($request);

        return match ($type) {
            'page' => $config->pageParam,
            'limit' => $config->limitParam,
            'cursor' => $config->cursorParam,
            default => null,
        };
    }

    private function resolveClientConfig(): ?ClientConfig
    {
        if ($this->context !== null) {
            return $this->context->config;
        }

        return $this->client?->getConfig();
    }

    private function setPaginationValue(
        AbstractRequest $request,
        ?string $property,
        mixed $value,
        bool $required,
    ): void {
        if ($property === null) {
            return;
        }

        if (!property_exists($request, $property)) {
            if ($required) {
                throw new ConfigurationException(new Message('request.property_not_found_for_pagination', ['property' => $property]));
            }
            return;
        }

        $reflection = new ReflectionProperty($request, $property);
        if ($reflection->isPublic()) {
            $request->{$property} = $value;
            return;
        }

        $reflection->setValue($request, $value);
    }

    private function resolveMetaResolver(PaginationConfig $config): ?PaginationMetaResolverInterface
    {
        $resolver = $config->metaResolver;
        if ($resolver === null) {
            return null;
        }

        if (is_string($resolver)) {
            if (!class_exists($resolver)) {
                throw new ConfigurationException(new Message('request.meta_resolver_class_not_found', ['resolver' => $resolver]));
            }
            $resolver = new $resolver();
        }

        if (!$resolver instanceof PaginationMetaResolverInterface) {
            throw new ConfigurationException(new Message('request.meta_resolver_must_implement_paginationmetaresolverinterface'));
        }

        return $resolver;
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMetaArray(array $response, PaginationConfig $config): array
    {
        $meta = ArrayPath::getByPath($response, $config->metaPath);
        if (!is_array($meta)) {
            return [];
        }

        return $meta;
    }
}
