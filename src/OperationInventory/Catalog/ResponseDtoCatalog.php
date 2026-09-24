<?php

declare(strict_types=1);

namespace ApiSutra\OperationInventory\Catalog;

use ApiSutra\Contracts\Interfaces\Inventory\OperationInventoryInterface;
use ApiSutra\Contracts\Interfaces\Inventory\ServiceClassResolverInterface;
use ApiSutra\Enums\Inventory\ResponseDtoKind;
use ApiSutra\OperationInventory\OperationDescriptorView;
use ApiSutra\VO\Files\FileResponse;

/**
 * Каталог response-DTO, выдаваемых SDK.
 *
 * Тонкий introspection-слой поверх OperationInventory:
 * - агрегирует все sync DTO (Returns) и async-final DTO (ContinuationResult)
 * - отдельно учитывает download-операции (response = FileResponse)
 * - возвращает плоские списки и usages, без какой-либо группировки/форматирования
 *
 * Любая презентационная логика (markdown, json, by-resource grouping) выполняется в
 * exporter-ах: каталог сам про форматирование ничего не знает.
 */
final readonly class ResponseDtoCatalog
{
    /** @var array<class-string, array<int, ResponseDtoUsage>> */
    private array $usagesByDto;

    public function __construct(
        private OperationInventoryInterface $inventory,
        private ?ServiceClassResolverInterface $serviceClassResolver = null,
    ) {
        $this->usagesByDto = $this->buildUsages();
    }

    public function inventory(): OperationInventoryInterface
    {
        return $this->inventory;
    }

    /**
     * @return array<int, class-string>
     */
    public function listAllDtoClasses(bool $includeDownload = false): array
    {
        $kinds = $includeDownload
            ? [ResponseDtoKind::Sync, ResponseDtoKind::AsyncFinal, ResponseDtoKind::Download]
            : [ResponseDtoKind::Sync, ResponseDtoKind::AsyncFinal];

        return $this->collectClassesByKinds($kinds);
    }

    /**
     * @return array<int, class-string>
     */
    public function listSyncDtoClasses(): array
    {
        return $this->collectClassesByKinds([ResponseDtoKind::Sync]);
    }

    /**
     * @return array<int, class-string>
     */
    public function listAsyncFinalDtoClasses(): array
    {
        return $this->collectClassesByKinds([ResponseDtoKind::AsyncFinal]);
    }

    /**
     * @return array<int, class-string>
     */
    public function listDownloadResponseClasses(): array
    {
        return $this->collectClassesByKinds([ResponseDtoKind::Download]);
    }

    /**
     * @return array<class-string, array<int, ResponseDtoUsage>>
     */
    public function usages(): array
    {
        return $this->usagesByDto;
    }

    /**
     * @return array<int, ResponseDtoUsage>
     */
    public function usagesFor(string $responseClass): array
    {
        return $this->usagesByDto[$responseClass] ?? [];
    }

    /**
     * @return array<class-string, array<int, ResponseDtoUsage>>
     */
    private function buildUsages(): array
    {
        $usages = [];

        foreach ($this->inventory->all() as $operation) {
            foreach ($this->extractUsagesFromOperation($operation) as $usage) {
                $usages[$usage->responseClass] ??= [];
                $usages[$usage->responseClass][] = $usage;
            }
        }

        foreach ($usages as $class => $items) {
            usort(
                $items,
                static fn (ResponseDtoUsage $a, ResponseDtoUsage $b): int => $a->requestClass <=> $b->requestClass,
            );
            $usages[$class] = $items;
        }

        ksort($usages);

        return $usages;
    }

    /**
     * @return iterable<int, ResponseDtoUsage>
     */
    private function extractUsagesFromOperation(OperationDescriptorView $operation): iterable
    {
        if ($operation->hasDownload) {
            yield $this->buildUsage(
                operation: $operation,
                responseClass: FileResponse::class,
                kind: ResponseDtoKind::Download,
            );

            return;
        }

        if ($operation->responseType !== null) {
            yield $this->buildUsage(
                operation: $operation,
                responseClass: $operation->responseType,
                kind: ResponseDtoKind::Sync,
                unwrap: $operation->returnsUnwrap,
            );
        }

        if ($operation->continuationFinalType !== null) {
            yield $this->buildUsage(
                operation: $operation,
                responseClass: $operation->continuationFinalType,
                kind: ResponseDtoKind::AsyncFinal,
                unwrap: $operation->continuationUnwrap,
                pollRequest: $operation->pollRequestClass,
            );
        }
    }

    private function buildUsage(
        OperationDescriptorView $operation,
        string $responseClass,
        ResponseDtoKind $kind,
        ?string $unwrap = null,
        ?string $pollRequest = null,
    ): ResponseDtoUsage {
        return new ResponseDtoUsage(
            requestClass: $operation->requestClass,
            resourcePath: $operation->resourcePath,
            resourceLabel: $operation->resourceLabel,
            httpMethod: $operation->httpMethod,
            endpoint: $operation->endpoint,
            title: $operation->title(),
            description: $operation->description(),
            responseClass: $responseClass,
            kind: $kind,
            pollRequest: $pollRequest,
            unwrap: $unwrap,
            serviceClass: $this->serviceClassResolver?->resolve($operation->requestClass),
        );
    }

    /**
     * @param  array<int, ResponseDtoKind> $kinds
     * @return array<int, class-string>
     */
    private function collectClassesByKinds(array $kinds): array
    {
        $classes = [];
        foreach ($this->usagesByDto as $class => $items) {
            $matches = array_any(
                $items,
                static fn (ResponseDtoUsage $usage): bool => in_array($usage->kind, $kinds, true),
            );
            if ($matches) {
                $classes[$class] = true;
            }
        }

        $list = array_keys($classes);
        sort($list);

        return $list;
    }
}
