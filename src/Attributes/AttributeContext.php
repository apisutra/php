<?php

declare(strict_types=1);

namespace ApiSutra\Attributes;

use ApiSutra\Enums\Attributes\AttributeContextType;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\VO\Pipeline\PipelineContext;
use ReflectionClass;
use ReflectionProperty;

readonly class AttributeContext
{
    /**
     * @param array<int, object> $classAttributes
     */
    public function __construct(
        public object $attribute,
        public ReflectionProperty|ReflectionClass $target,
        public AttributeContextType $type,
        public PipelineStage $stage,
        public PipelineContext $context,
        public mixed $data = null,
        public array $classAttributes = [],
    ) {
    }
}
