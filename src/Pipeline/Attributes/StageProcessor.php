<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Attributes;

use ApiSutra\Attributes\AttributeRegistry;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\VO\Pipeline\PipelineContext;

final readonly class StageProcessor
{
    public function __construct(
        private AttributeRegistry $attributes,
    ) {
    }

    public function process(
        object $target,
        PipelineContext $context,
        PipelineStage $stage,
        mixed $data = null,
    ): mixed {
        return $this->attributes->processStage($target, $context, $stage, $data);
    }
}
