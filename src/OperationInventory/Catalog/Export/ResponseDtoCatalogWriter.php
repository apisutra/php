<?php

declare(strict_types=1);

namespace ApiSutra\OperationInventory\Catalog\Export;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Inventory\ResponseDtoCatalogExporterInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\OperationInventory\Catalog\ResponseDtoCatalog;

/**
 * Тонкий facade над набором exporter-ов для записи каталога в файл / рендера в строку.
 *
 * Принципы:
 * - сам ничего не форматирует
 * - выбирает exporter по format-id
 * - format может быть передан явно или выведен по расширению пути
 * - в случае конфликтов / отсутствия exporter-а — кидает ConfigurationException
 */
final readonly class ResponseDtoCatalogWriter
{
    /** @var array<string, ResponseDtoCatalogExporterInterface> */
    private array $exportersByFormat;

    /**
     * @param array<int, ResponseDtoCatalogExporterInterface> $exporters
     */
    public function __construct(array $exporters)
    {
        $this->exportersByFormat = $this->indexExporters($exporters);
    }

    public function writeTo(
        string $path,
        ResponseDtoCatalog $catalog,
        ?string $format = null,
    ): void {
        $resolvedFormat = $this->resolveFormat($path, $format);
        $content = $this->render($catalog, $resolvedFormat);

        $directory = dirname($path);
        if ($directory !== '' && $directory !== '.' && !is_dir($directory)) {
            if (!mkdir($directory, 0775, true) && !is_dir($directory)) {
                throw new ConfigurationException(new Message('operationinventory.failed_to_create_directory_s', ['directory' => $directory]));
            }
        }

        if (file_put_contents($path, $content) === false) {
            throw new ConfigurationException(new Message('operationinventory.failed_to_write_catalog_to_file_s', ['path' => $path]));
        }
    }

    public function render(ResponseDtoCatalog $catalog, string $format): string
    {
        $normalized = $this->normalizeFormat($format);
        $exporter = $this->exportersByFormat[$normalized] ?? null;
        if ($exporter === null) {
            throw new ConfigurationException(new Message('operationinventory.no_exporter_for_format_s_available_s', ['format' => $format, 'value1' => implode(', ', array_keys($this->exportersByFormat))]));
        }

        return $exporter->export($catalog);
    }

    /**
     * @return array<int, string>
     */
    public function formats(): array
    {
        return array_keys($this->exportersByFormat);
    }

    private function resolveFormat(string $path, ?string $explicit): string
    {
        if ($explicit !== null && $explicit !== '') {
            return $this->normalizeFormat($explicit);
        }

        $extension = pathinfo($path, PATHINFO_EXTENSION);
        if (!is_string($extension) || $extension === '') {
            throw new ConfigurationException(new Message('operationinventory.format_is_not_specified_and_cannot_be_inferred_from', ['path' => $path]));
        }

        return $this->normalizeFormat($extension);
    }

    private function normalizeFormat(string $format): string
    {
        return strtolower(ltrim($format, '.'));
    }

    /**
     * @param  array<int, ResponseDtoCatalogExporterInterface>      $exporters
     * @return array<string, ResponseDtoCatalogExporterInterface>
     */
    private function indexExporters(array $exporters): array
    {
        $indexed = [];
        foreach ($exporters as $exporter) {
            $format = $this->normalizeFormat($exporter->format());
            if ($format === '') {
                throw new ConfigurationException(new Message('operationinventory.exporter_s_returned_an_empty_format', ['value0' => $exporter::class]));
            }
            if (isset($indexed[$format])) {
                throw new ConfigurationException(new Message('operationinventory.duplicate_exporter_for_format_s_s_and_s', ['format' => $format, 'value1' => $indexed[$format]::class, 'value2' => $exporter::class]));
            }
            $indexed[$format] = $exporter;
        }

        return $indexed;
    }
}
