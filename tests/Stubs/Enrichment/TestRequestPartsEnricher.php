<?php

declare(strict_types=1);

namespace ApiSutra\Tests\Stubs\Enrichment;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Serialization\RequestPartsEnricherInterface;
use ApiSutra\Serialization\VO\RequestPartsBag;
use ApiSutra\VO\Pipeline\PipelineContext;

final readonly class TestRequestPartsEnricher implements RequestPartsEnricherInterface
{
    public function __construct(
        private string $queryKey,
        private string $queryValue,
    ) {}

    #[\Override]
    public function enrich(
        RequestInterface $request,
        RequestPartsBag $parts,
        ?PipelineContext $context = null,
    ): RequestPartsBag {
        $parts->query[$this->queryKey] = [
            'value' => $this->queryValue,
            'format' => null,
        ];

        return $parts;
    }
}
