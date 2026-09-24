<?php

declare(strict_types=1);

namespace ApiSutra\Contracts\Interfaces\Serialization;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Serialization\VO\RequestPartsBag;
use ApiSutra\VO\Pipeline\PipelineContext;

interface RequestPartsEnricherInterface
{
    public function enrich(
        RequestInterface $request,
        RequestPartsBag $parts,
        ?PipelineContext $context = null,
    ): RequestPartsBag;
}
