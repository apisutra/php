<?php

declare(strict_types=1);

namespace ApiSutra\Attributes;

use ApiSutra\Metadata\MetadataCatalog;

final class AttributeMetadataCache
{
    /**
     * @var array<string, array>
     */
    private array $cache = [];
    private readonly MetadataCatalog $catalog;

    public function __construct(
        private readonly bool $enabled = true,
    ) {
        $this->catalog = new MetadataCatalog($enabled);
    }

    /** @internal Общая структура отделена от направленных шаблонов в get()/set(). */
    public function catalog(): MetadataCatalog
    {
        return $this->catalog;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function get(string $class): ?array
    {
        return $this->enabled ? ($this->cache[$class] ?? null) : null;
    }

    public function set(string $class, array $data): void
    {
        if ($this->enabled) {
            $this->cache[$class] = $data;
        }
    }

    /**
     * Предварительно заполнить кеш метаданными
     * @param array<int, class-string> $classes
     */
    public function warmup(array $classes): void
    {
        if (!$this->enabled) {
            return;
        }

        foreach ($classes as $class) {
            if (!class_exists($class) || isset($this->cache[$class])) {
                continue;
            }

            $this->cache[$class] = $this->catalog->forClass($class)->registryMetadata();
        }
    }
}
