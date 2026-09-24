<?php

declare(strict_types=1);

namespace ApiSutra\Metadata;

use ReflectionClass;

/** @internal Единственный структурный обход; общий cache не содержит направленных правил. */
final class MetadataCatalog
{
    /** @var array<string, ClassMetadata> */
    private array $descriptions = [];

    public function __construct(private readonly bool $enabled = false)
    {
    }

    public function forClass(string $class): ClassMetadata
    {
        if (isset($this->descriptions[$class])) {
            return $this->descriptions[$class];
        }
        $description = new ClassMetadata(new ReflectionClass($class));
        if ($this->enabled) {
            $this->descriptions[$class] = $description;
        }
        return $description;
    }
}
