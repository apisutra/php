<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Preparation;

use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Serialization\Serializer;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Pipeline\PipelineContext;

final readonly class PreparedRequestFactory
{
    public function __construct(
        private Serializer $serializer,
        private RequestPreparer $requestPreparer,
    ) {
    }

    public function create(RequestInterface $request, PipelineContext $context): PreparedRequest
    {
        $prepared = $this->serializer->serialize($request, $context);

        return $this->requestPreparer->applyRequestOverrides($request, $prepared, $context->options)
            ->with(transportOptions: TimeoutResolver::resolve($context));
    }
}
