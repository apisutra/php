<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Flow;

use ApiSutra\Diagnostics\SensitiveFields;
use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Preparation\PreparedRequestFactory;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LogLevel;

final readonly class RequestPreparationStep
{
    public function __construct(
        private PreparedRequestFactory $preparedRequestFactory,
        private AuditLogger $auditLogger,
    ) {
    }

    public function prepare(RequestInterface $request, PipelineContext $context): PreparedRequest
    {
        $prepared = $this->preparedRequestFactory->create($request, $context);
        $context->preparedRequest = $prepared;

        $secretFields = SensitiveFields::of($prepared);
        $this->auditLogger->log(LogLevel::DEBUG, new Message('pipeline.http_request_prepared'), [
            ...$context->trace->logContext(),
            'method' => $prepared->method->value,
            'url' => $prepared->destination?->preserveUrl ? $prepared->destination->diagnosticUrl() : $prepared->url,
        ], $secretFields);

        return $prepared;
    }
}
