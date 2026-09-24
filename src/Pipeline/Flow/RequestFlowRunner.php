<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Flow;

use ApiSutra\Pipeline\Hydration\ResponseContractGuard;
use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResultMeta;
use ApiSutra\Contracts\Interfaces\Pagination\PaginableInterface;
use ApiSutra\Enums\Hooks\Hook;
use ApiSutra\Http\DestinationGuard;
use ApiSutra\Files\FileTransferGuard;
use ApiSutra\Files\DownloadManager;
use ApiSutra\Enums\Pipeline\PipelineStage;
use ApiSutra\Pipeline\Attributes\StageProcessor;
use ApiSutra\Pipeline\Auth\AuthHandler;
use ApiSutra\Pipeline\Cache\CacheManager;
use ApiSutra\Pipeline\Diagnostics\AuditLogger;
use ApiSutra\Pipeline\Error\ErrorPolicy;
use ApiSutra\Pipeline\Hooks\HookRunner;
use ApiSutra\Pipeline\Hydration\ResponseHydrator;
use ApiSutra\Pipeline\Result\ResultFactory;
use ApiSutra\Pipeline\Transport\RetrySender;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\VO\Pipeline\PipelineContext;
use Psr\Log\LogLevel;

/**
 * Линейный исполнитель "середины" пайплайна после подготовки PreparedRequest.
 *
 * Порядок (инвариант):
 * - beforeSend stages -> auth -> hooks;
 * - cache hit или sendWithRetry;
 * - error policy check;
 * - hydrate + post-hydrate stages/hooks;
 * - finalize success result (+ cache store).
 *
 * @see docs/technical/pipeline.md
 * @see docs/guides/request-pipeline.md
 */
final readonly class RequestFlowRunner
{
    public function __construct(
        private StageProcessor $stageProcessor,
        private AuthHandler $authHandler,
        private HookRunner $hookRunner,
        private CacheManager $cacheManager,
        private RetrySender $retrySender,
        private ErrorPolicy $errorPolicy,
        private ResponseHydrator $responseHydrator,
        private ResultFactory $resultFactory,
        private AuditLogger $auditLogger,
        private ExecutionResultBuilder $resultBuilder,
    ) {
    }

    public function run(
        RequestInterface $request,
        PipelineContext $context,
        array &$audit,
        float $startTime,
        PreparedRequest $prepared,
    ): ExecutionResult {
        $this->retrySender->assertDestinationSupported($context);
        $this->cacheManager->prepareExecution($request, $context);
        $context->budget?->check('cache_prepare');
        $prepared = $this->applyBeforeSendStages($request, $context, $prepared);

        DestinationGuard::checkContext($context);
        FileTransferGuard::checkContext($context);
        $cachedResponse = $this->resolveResponse($request, $context);
        $context->budget?->check('response');
        $this->logResponse($context, $cachedResponse !== null);

        $failedResult = $this->handleFailedResponse($request, $context, $audit);
        if ($failedResult instanceof ExecutionResult) {
            return $failedResult;
        }

        $resultData = $this->hydrateAndProcessDto($request, $context);

        return $this->finalizeSuccessResult(
            request: $request,
            context: $context,
            audit: $audit,
            startTime: $startTime,
            prepared: $prepared,
            resultData: $resultData,
        );
    }

    private function applyBeforeSendStages(
        RequestInterface $request,
        PipelineContext $context,
        PreparedRequest $prepared,
    ): PreparedRequest {
        $processed = $this->stageProcessor->process(
            $request,
            $context,
            PipelineStage::BeforeSend,
            $context->preparedRequest,
        );
        $this->applyPreparedResult($processed, $context);
        $context->budget?->check('before_send');
        $prepared = $this->syncPrepared($context, $prepared);
        $this->authHandler->handleAuthentication($request, $context);
        $context->budget?->check('authentication');
        $prepared = $this->syncPrepared($context, $prepared);
        $this->hookRunner->runHookStage(Hook::BeforeSend, $request, $context);
        $context->budget?->check('before_send');

        return $prepared;
    }

    private function applyPreparedResult(mixed $processed, PipelineContext $context): void
    {
        if ($processed instanceof PreparedRequest) {
            $context->preparedRequest = $processed;
        }
    }

    private function syncPrepared(PipelineContext $context, PreparedRequest $prepared): PreparedRequest
    {
        if ($context->preparedRequest instanceof PreparedRequest) {
            return $context->preparedRequest;
        }

        return $prepared;
    }

    private function resolveResponse(RequestInterface $request, PipelineContext $context): ?ProviderResponse
    {
        $cachedResponse = $this->cacheManager->checkCache($request, $context);
        $context->budget?->check('cache_lookup');
        if ($cachedResponse instanceof ProviderResponse) {
            $context->response = $cachedResponse;
            $context->lastResponse = $cachedResponse;
            $this->hookRunner->runHookStage(Hook::AfterResponse, $request, $context);
            return $cachedResponse;
        }

        $context->response = $this->retrySender->sendWithRetry($request, $context);

        return null;
    }

    private function logResponse(PipelineContext $context, bool $cached): void
    {
        if ($context->response === null) {
            return;
        }

        $this->auditLogger->log(LogLevel::DEBUG, new Message('pipeline.http_response_received'), [
            ...$context->trace->logContext(),
            'status' => $context->response->status,
            'cached' => $cached,
            'duration_ms' => $context->response->duration,
        ]);
    }

    private function handleFailedResponse(
        RequestInterface $request,
        PipelineContext $context,
        array &$audit,
    ): ?ExecutionResult {
        if (!$this->errorPolicy->hasRequestFailed($request, $context->response)) {
            return null;
        }

        $result = $this->resultFactory->buildFailedResult($request, $context, $audit);
        return $result;
    }

    private function hydrateAndProcessDto(RequestInterface $request, PipelineContext $context): mixed
    {
        $decoded = $this->responseHydrator->decodeResponse($request, $context);
        $data = $decoded->data;
        if (is_array($data)) {
            $data = $this->hookRunner->runBeforeHydrate($request, $context, $data);
        }

        $context->budget?->check('before_hydrate');
        $resultData = $this->responseHydrator->hydrateResponse($request, $context, $data, $decoded);
        $context->budget?->check('hydration');
        $context->dto = is_object($resultData) ? $resultData : null;
        if ($context->dto !== null) {
            $processed = $this->stageProcessor->process(
                $context->dto,
                $context,
                PipelineStage::AfterHydrate,
                $context->dto,
            );
            if (is_object($processed)) {
                $context->dto = $processed;
                $resultData = $processed;
            }
        }

        $context->budget?->check('after_hydrate');
        $this->hookRunner->runHookStage(Hook::AfterHydrate, $request, $context);
        $context->budget?->check('after_hydrate');

        return $resultData;
    }

    private function finalizeSuccessResult(
        RequestInterface $request,
        PipelineContext $context,
        array &$audit,
        float $startTime,
        PreparedRequest $prepared,
        mixed $resultData,
    ): ExecutionResult {
        $meta = $this->resolveMeta($request, $context);
        ResponseContractGuard::check($request, $context->config, $resultData);
        $context->budget?->check('before_cache_store');
        $this->cacheManager->storeCache($request, $context);
        $context->budget?->check('cache_store');

        if ($context->fileTransfer?->download && $context->response !== null) {
            DownloadManager::deliver($context->response, $context->fileTransfer->target, $context->budget);
        }
        $result = $this->resultBuilder->buildSuccessResult(
            request: $request,
            context: $context,
            audit: $audit,
            startTime: $startTime,
            prepared: $prepared,
            data: $resultData,
            meta: $meta,
        );
        return $result;
    }

    private function resolveMeta(RequestInterface $request, PipelineContext $context): ?ResultMeta
    {
        if (!$request instanceof PaginableInterface) {
            return null;
        }

        return $request->extractMeta($context->response?->json() ?? []);
    }
}
