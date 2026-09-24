<?php

declare(strict_types=1);

namespace ApiSutra\OperationInventory\Catalog\Export;

use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Inventory\ResponseDtoCatalogExporterInterface;
use ApiSutra\Contracts\Interfaces\Inventory\ServiceLabelResolverInterface;
use ApiSutra\Enums\Inventory\ResponseDtoKind;
use ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;
use ApiSutra\OperationInventory\Catalog\ResponseDtoUsage;
use ApiSutra\OperationInventory\Catalog\ShortClassServiceLabelResolver;

/**
 * Built-in Markdown-экспортёр каталога response DTO.
 *
 * Layout:
 * 1. Header + summary (счётчики по kind)
 * 2. (опционально) Service breakdown — только в multi-service режиме
 * 3. By-resource sections с поддержкой вложенных ресурсов
 * 4. By-DTO usages
 * 5. Download responses (если есть)
 *
 * Сортировка стабильная: по resourceLabel, затем по requestClass.
 *
 * Multi-service: активируется автоматически, если в каталоге есть хотя бы один
 * ResponseDtoUsage с заполненным serviceClass. В этом режиме во всех таблицах
 * добавляется колонка Service. В single-client режиме layout остаётся
 * полностью идентичным предыдущему (zero-impact regression).
 */
final readonly class MarkdownResponseDtoCatalogExporter implements ResponseDtoCatalogExporterInterface
{
    private const string FORMAT = 'md';

    private ServiceLabelResolverInterface $labelResolver;

    public function __construct(
        ?ServiceLabelResolverInterface $labelResolver = null,
        private LocalizationConfig $localization = new LocalizationConfig(),
    ) {
        $this->labelResolver = $labelResolver ?? new ShortClassServiceLabelResolver();
    }

    #[\Override]
    public function format(): string
    {
        return self::FORMAT;
    }

    #[\Override]
    public function export(ResponseDtoCatalog $catalog): string
    {
        $isMultiService = $this->isMultiService($catalog);

        $sections = [];
        $sections[] = $this->renderHeader($catalog);
        if ($isMultiService) {
            $sections[] = $this->renderServicesSummary($catalog);
        }
        $sections[] = $this->renderByResource($catalog, $isMultiService);
        $sections[] = $this->renderByDto($catalog, $isMultiService);
        $sections[] = $this->renderDownloads($catalog, $isMultiService);

        $sections = array_values(array_filter(
            $sections,
            static fn (string $section): bool => $section !== '',
        ));

        return implode("\n\n", $sections) . "\n";
    }

    private function isMultiService(ResponseDtoCatalog $catalog): bool
    {
        foreach ($catalog->usages() as $items) {
            foreach ($items as $usage) {
                if ($usage->serviceClass !== null) {
                    return true;
                }
            }
        }

        return false;
    }

    private function renderHeader(ResponseDtoCatalog $catalog): string
    {
        $sync = count($catalog->listSyncDtoClasses());
        $async = count($catalog->listAsyncFinalDtoClasses());
        $download = count($catalog->listDownloadResponseClasses());

        $lines = [];
        $lines[] = (new Message('operationinventory.response_dto_catalog'))->render($this->localization);
        $lines[] = '';
        $lines[] = '| Kind | Count |';
        $lines[] = '| --- | ---: |';
        $lines[] = sprintf('| Sync | %d |', $sync);
        $lines[] = sprintf('| AsyncFinal | %d |', $async);
        $lines[] = sprintf('| Download | %d |', $download);

        return implode("\n", $lines);
    }

    private function renderServicesSummary(ResponseDtoCatalog $catalog): string
    {
        $perService = $this->collectPerServiceCounters($catalog);
        if ($perService === []) {
            return '';
        }

        ksort($perService);

        $lines = [(new Message('operationinventory.services'))->render($this->localization)];
        $lines[] = '';
        $lines[] = '| Service | Sync | AsyncFinal | Download |';
        $lines[] = '| --- | ---: | ---: | ---: |';
        foreach ($perService as $serviceClass => $counts) {
            $lines[] = sprintf(
                '| %s | %d | %d | %d |',
                $this->labelResolver->resolve($serviceClass),
                $counts['sync'],
                $counts['async'],
                $counts['download'],
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, array{sync:int, async:int, download:int}>
     */
    private function collectPerServiceCounters(ResponseDtoCatalog $catalog): array
    {
        $counters = [];

        foreach ($catalog->usages() as $items) {
            foreach ($items as $usage) {
                if ($usage->serviceClass === null) {
                    continue;
                }
                $counters[$usage->serviceClass] ??= ['sync' => 0, 'async' => 0, 'download' => 0];
                $counters[$usage->serviceClass][match ($usage->kind) {
                    ResponseDtoKind::Sync => 'sync',
                    ResponseDtoKind::AsyncFinal => 'async',
                    ResponseDtoKind::Download => 'download',
                }]++;
            }
        }

        return $counters;
    }

    private function renderByResource(ResponseDtoCatalog $catalog, bool $multiService): string
    {
        $byGroup = $this->groupOperationsByResource($catalog);
        if ($byGroup === []) {
            return '';
        }

        $lines = [(new Message('operationinventory.operations_by_resource'))->render($this->localization)];
        $headerCells = $multiService
            ? '| Service | Request | HTTP | Endpoint | Kind | Response | Title |'
            : '| Request | HTTP | Endpoint | Kind | Response | Title |';
        $headerSep = $multiService
            ? '| --- | --- | --- | --- | --- | --- | --- |'
            : '| --- | --- | --- | --- | --- | --- |';

        foreach ($byGroup as $resourceLabel => $usages) {
            $lines[] = '';
            $lines[] = sprintf('### %s', $resourceLabel);
            $lines[] = '';
            $lines[] = $headerCells;
            $lines[] = $headerSep;

            foreach ($usages as $usage) {
                if ($multiService) {
                    $lines[] = sprintf(
                        '| %s | `%s` | %s | %s | %s | `%s` | %s |',
                        $this->serviceLabel($usage),
                        $this->shortClass($usage->requestClass),
                        $usage->httpMethod->value ?? '—',
                        $usage->endpoint !== null ? sprintf('`%s`', $usage->endpoint) : '—',
                        $usage->kind->value,
                        $this->shortClass($usage->responseClass),
                        $this->escapeCell($usage->title ?? '—'),
                    );
                    continue;
                }

                $lines[] = sprintf(
                    '| `%s` | %s | %s | %s | `%s` | %s |',
                    $this->shortClass($usage->requestClass),
                    $usage->httpMethod->value ?? '—',
                    $usage->endpoint !== null ? sprintf('`%s`', $usage->endpoint) : '—',
                    $usage->kind->value,
                    $this->shortClass($usage->responseClass),
                    $this->escapeCell($usage->title ?? '—'),
                );
            }
        }

        return implode("\n", $lines);
    }

    private function renderByDto(ResponseDtoCatalog $catalog, bool $multiService): string
    {
        $usages = $catalog->usages();
        $dtoUsages = array_filter(
            $usages,
            static function (array $items): bool {
                return array_any(
                    $items,
                    static fn (ResponseDtoUsage $usage): bool => $usage->kind !== ResponseDtoKind::Download,
                );
            },
        );

        if ($dtoUsages === []) {
            return '';
        }

        $lines = ['## DTO → request usages'];
        $headerCells = $multiService
            ? '| Service | Request | Kind | Poll request | Unwrap |'
            : '| Request | Kind | Poll request | Unwrap |';
        $headerSep = $multiService
            ? '| --- | --- | --- | --- | --- |'
            : '| --- | --- | --- | --- |';

        foreach ($dtoUsages as $dtoClass => $items) {
            $relevant = array_values(array_filter(
                $items,
                static fn (ResponseDtoUsage $usage): bool => $usage->kind !== ResponseDtoKind::Download,
            ));
            if ($relevant === []) {
                continue;
            }

            $lines[] = '';
            $lines[] = sprintf('### `%s`', $dtoClass);
            $lines[] = '';
            $lines[] = $headerCells;
            $lines[] = $headerSep;

            foreach ($relevant as $usage) {
                if ($multiService) {
                    $lines[] = sprintf(
                        '| %s | `%s` | %s | %s | %s |',
                        $this->serviceLabel($usage),
                        $this->shortClass($usage->requestClass),
                        $usage->kind->value,
                        $usage->pollRequest !== null ? sprintf('`%s`', $this->shortClass($usage->pollRequest)) : '—',
                        $usage->unwrap !== null ? sprintf('`%s`', $usage->unwrap) : '—',
                    );
                    continue;
                }

                $lines[] = sprintf(
                    '| `%s` | %s | %s | %s |',
                    $this->shortClass($usage->requestClass),
                    $usage->kind->value,
                    $usage->pollRequest !== null ? sprintf('`%s`', $this->shortClass($usage->pollRequest)) : '—',
                    $usage->unwrap !== null ? sprintf('`%s`', $usage->unwrap) : '—',
                );
            }
        }

        return implode("\n", $lines);
    }

    private function renderDownloads(ResponseDtoCatalog $catalog, bool $multiService): string
    {
        $downloadUsages = [];
        foreach ($catalog->usages() as $items) {
            foreach ($items as $usage) {
                if ($usage->kind === ResponseDtoKind::Download) {
                    $downloadUsages[] = $usage;
                }
            }
        }

        if ($downloadUsages === []) {
            return '';
        }

        usort(
            $downloadUsages,
            static fn (ResponseDtoUsage $a, ResponseDtoUsage $b): int => $a->requestClass <=> $b->requestClass,
        );

        $lines = ['## Download responses'];
        $lines[] = '';
        if ($multiService) {
            $lines[] = '| Service | Request | HTTP | Endpoint | Response | Resource |';
            $lines[] = '| --- | --- | --- | --- | --- | --- |';
        } else {
            $lines[] = '| Request | HTTP | Endpoint | Response | Resource |';
            $lines[] = '| --- | --- | --- | --- | --- |';
        }
        foreach ($downloadUsages as $usage) {
            if ($multiService) {
                $lines[] = sprintf(
                    '| %s | `%s` | %s | %s | `%s` | %s |',
                    $this->serviceLabel($usage),
                    $this->shortClass($usage->requestClass),
                    $usage->httpMethod->value ?? '—',
                    $usage->endpoint !== null ? sprintf('`%s`', $usage->endpoint) : '—',
                    $this->shortClass($usage->responseClass),
                    $this->escapeCell($usage->resourceLabel ?? '—'),
                );
                continue;
            }

            $lines[] = sprintf(
                '| `%s` | %s | %s | `%s` | %s |',
                $this->shortClass($usage->requestClass),
                $usage->httpMethod->value ?? '—',
                $usage->endpoint !== null ? sprintf('`%s`', $usage->endpoint) : '—',
                $this->shortClass($usage->responseClass),
                $this->escapeCell($usage->resourceLabel ?? '—'),
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @return array<string, array<int, ResponseDtoUsage>>
     */
    private function groupOperationsByResource(ResponseDtoCatalog $catalog): array
    {
        $perRequestUsage = [];

        foreach ($catalog->usages() as $items) {
            foreach ($items as $usage) {
                if ($usage->kind === ResponseDtoKind::Download) {
                    continue;
                }
                $perRequestUsage[$usage->requestClass] ??= $usage;
            }
        }

        $byResource = [];
        foreach ($perRequestUsage as $usage) {
            $label = $usage->resourceLabel ?? (new Message('operationinventory.no_resource'))->render($this->localization);
            $byResource[$label] ??= [];
            $byResource[$label][] = $usage;
        }

        ksort($byResource);
        foreach ($byResource as $label => $items) {
            usort(
                $items,
                static fn (ResponseDtoUsage $a, ResponseDtoUsage $b): int => $a->requestClass <=> $b->requestClass,
            );
            $byResource[$label] = $items;
        }

        return $byResource;
    }

    private function serviceLabel(ResponseDtoUsage $usage): string
    {
        if ($usage->serviceClass === null) {
            return '—';
        }

        return $this->escapeCell($this->labelResolver->resolve($usage->serviceClass));
    }

    private function shortClass(string $fqn): string
    {
        $position = strrpos($fqn, '\\');

        return $position === false ? $fqn : substr($fqn, $position + 1);
    }

    private function escapeCell(string $value): string
    {
        return str_replace(['|', "\n"], ['\\|', ' '], $value);
    }
}
