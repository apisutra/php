<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\ResultContract;

use ApiSutra\Attributes\AttributeContext;
use ApiSutra\Contracts\Interfaces\Attributes\AttributeContextHandlerInterface;
use ApiSutra\Enums\Pipeline\PipelineStage;
use stdClass;

final class ReplaceResultHandler implements AttributeContextHandlerInterface
{
    public function handle(AttributeContext $context): mixed
    {
        return $context->stage === PipelineStage::AfterHydrate ? new stdClass() : $context->data;
    }
}
