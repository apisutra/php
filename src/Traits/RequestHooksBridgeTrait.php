<?php

declare(strict_types=1);

namespace ApiSutra\Traits;

use ApiSutra\VO\Pipeline\PipelineContext;

/**
 * Базовые hook‑методы и internal‑мосты.
 */
trait RequestHooksBridgeTrait
{
    protected function beforeSend(PipelineContext $context): void
    {
    }

    protected function afterResponse(PipelineContext $context): void
    {
    }

    protected function beforeHydrate(PipelineContext $context, array $data): array
    {
        return $data;
    }

    protected function afterHydrate(PipelineContext $context): void
    {
    }

    public function beforeSendInternal(PipelineContext $context): void
    {
        $this->beforeSend($context);
    }

    public function afterResponseInternal(PipelineContext $context): void
    {
        $this->afterResponse($context);
    }

    public function beforeHydrateInternal(PipelineContext $context, array $data): array
    {
        return $this->beforeHydrate($context, $data);
    }

    public function afterHydrateInternal(PipelineContext $context): void
    {
        $this->afterHydrate($context);
    }
}
