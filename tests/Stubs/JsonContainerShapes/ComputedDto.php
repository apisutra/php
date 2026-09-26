<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\JsonContainerShapes;

use ApiSutra\VO\Pipeline\PipelineContext;
use Override;

final readonly class ComputedDto extends ResponseDto
{
    #[Override]
    public static function computed(array $data, ?PipelineContext $context = null): array
    {
        return $data;
    }
}
