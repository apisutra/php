<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Execution;

use ApiSutra\Localization\Message;
use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Collections\ResultCollection;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Core\CompositeRequestInterface;
use ApiSutra\Contracts\Interfaces\Core\DependsOnRequestInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Execution\ExecutionMode;
use ApiSutra\Enums\Execution\FailStrategy;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\ResponseTypeMismatchException;
use ApiSutra\Execution\CompositeExecutor;
use ApiSutra\Execution\DependsOnExecutor;
use ApiSutra\Pipeline\Hydration\ResponseContractGuard;
use ApiSutra\Pipeline\Hydration\ResponseDtoHydratorResolver;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\VO\Errors\RequestError;
use ApiSutra\VO\Errors\SystemErrorContextBuilder;
use ApiSutra\VO\Pipeline\PipelineContext;

final readonly class CompositeFlow
{
    public function __construct(
        private Hydrator $hydrator,
        private ResponseDtoHydratorResolver $dtoHydrators = new ResponseDtoHydratorResolver(),
    ) {
    }

    public function executeComposite(
        CompositeRequestInterface $request,
        PipelineContext $context,
    ): ExecutionResult {
        [$mode, $strategy] = $this->resolveExecutionConfig($request);
        $resultCollection = $this->executeCompositeRequests($request, $context, $mode, $strategy);
        $context->nested = $resultCollection->all();

        if ($this->shouldFail($strategy, $resultCollection)) {
            return $this->buildCompositeResult($request, $context, $resultCollection, ResultStatus::FAILED);
        }

        $context->budget?->check('composite');
        $data = $this->aggregateCompositeData($request, $resultCollection, $context);
        $status = $this->resolveCompositeStatus($resultCollection);

        return $this->buildCompositeResult($request, $context, $resultCollection, $status, $data);
    }

    public function executeDependsOn(
        DependsOnRequestInterface $request,
        PipelineContext $context,
    ): ?ExecutionResult {
        [$mode, $strategy] = $this->resolveExecutionConfig($request);
        $resultCollection = $this->executeDependencies($request, $context, $mode, $strategy);

        $context->nested = $resultCollection->all();
        if ($this->shouldFail($strategy, $resultCollection)) {
            return $this->buildCompositeResult($request, $context, $resultCollection, ResultStatus::FAILED);
        }

        $context->budget?->check('dependencies');
        $this->processDependencies($request, $resultCollection, $context);
        $context->budget?->check('dependencies');

        return null;
    }

    private function resolveExecutionConfig(RequestInterface $request): array
    {
        $execution = $request instanceof AbstractRequest
            ? $request->getExecutionAttribute()
            : null;

        return [
            $execution->mode ?? ExecutionMode::Sequential,
            $execution->failStrategy ?? FailStrategy::FailAll,
        ];
    }

    private function resolveClient(PipelineContext $context, string|Message $message): ClientInterface
    {
        $client = $context->request instanceof AbstractRequest ? $context->request->getClient() : null;
        if ($client === null) {
            throw new ConfigurationException($message);
        }

        return $client;
    }

    private function executeCompositeRequests(
        CompositeRequestInterface $request,
        PipelineContext $context,
        ExecutionMode $mode,
        FailStrategy $strategy,
    ): ResultCollection {
        $client = $this->resolveClient($context, new Message('pipeline.no_client_specified_for_composite_execution'));
        $executor = new CompositeExecutor(
            client: $client,
            requests: $request->requests(),
            parent: $context,
        );

        return $executor->execute($mode, $strategy);
    }

    private function executeDependencies(
        DependsOnRequestInterface $request,
        PipelineContext $context,
        ExecutionMode $mode,
        FailStrategy $strategy,
    ): ResultCollection {
        $client = $this->resolveClient($context, new Message('pipeline.no_client_specified_for_depends_on_execution'));
        $executor = new DependsOnExecutor(
            client: $client,
            requests: $request->dependencies(),
            parent: $context,
        );

        return $executor->execute($mode, $strategy);
    }

    private function shouldFail(FailStrategy $strategy, ResultCollection $results): bool
    {
        return $strategy === FailStrategy::FailAll && $results->hasErrors();
    }

    private function resolveCompositeStatus(ResultCollection $results): ResultStatus
    {
        return $results->hasErrors() ? ResultStatus::PARTIAL : ResultStatus::SUCCESS;
    }

    private function aggregateCompositeData(
        CompositeRequestInterface $request,
        ResultCollection $results,
        PipelineContext $context,
    ): mixed {
        return $request->aggregate($results, $context);
    }

    private function processDependencies(
        DependsOnRequestInterface $request,
        ResultCollection $results,
        PipelineContext $context,
    ): void {
        $request->processDependencies($results, $context);
    }

    private function buildCompositeResult(
        RequestInterface $request,
        PipelineContext $context,
        ResultCollection $results,
        ResultStatus $status,
        mixed $data = null,
    ): ExecutionResult {
        $errors = $this->collectErrors($results, $request, $context);
        $nested = $results->all();
        $meta = $results->toBatchMeta();
        $resultData = $this->hydrateResultData($request, $context, $data);
        $exception = null;
        if ($status === ResultStatus::SUCCESS) {
            try {
                ResponseContractGuard::check($request, $context->config, $resultData);
            } catch (ResponseTypeMismatchException $failure) {
                $exception = $failure;
                $status = ResultStatus::FAILED;
                $resultData = null;
                $errors[] = new RequestError(
                    code: ErrorCode::HydrationError,
                    message: $failure->messageDefinition() ?? $failure->getMessage(),
                    localization: $context->config->localization,
                    requestClass: $request::class,
                    context: $failure->context() + [
                        'traceId' => $context->traceId,
                        'requestClass' => $request::class,
                        'httpStatus' => null,
                        'transmissionState' => $context->transmissionState->value,
                    ],
                );
            }
        }

        $result = new ExecutionResult(
            exceptionFactory: $context->config->resultExceptions?->exceptionFactory,
            exception: $exception ?? ($status === ResultStatus::FAILED ? $results->firstFailure()?->exception : null),
            data: $resultData,
            status: $status,
            errors: new ErrorCollection($errors),
            traceId: $context->traceId,
            trace: $context->trace,
            meta: $meta,
            nested: $nested,
            requestClass: $request::class,
        );
        return $result;
    }

    private function collectErrors(
        ResultCollection $results,
        RequestInterface $request,
        PipelineContext $context,
    ): array {
        $errors = [];
        foreach ($results->all() as $result) {
            if ($result->isFailed()) {
                $firstError = $result->errors->first();
                if ($firstError !== null) {
                    $errors[] = $firstError;
                    continue;
                }

                $contextData = SystemErrorContextBuilder::build(
                    traceId: $context->traceId,
                    httpStatus: null,
                    requestClass: $request::class,
                );

                $errors[] = new RequestError(
                    code: ErrorCode::ServerError,
                    message: new Message('pipeline.nested_request_failed'),
                    context: $contextData,
                    requestClass: $request::class,
                );
            }
        }

        return $errors;
    }

    private function hydrateResultData(RequestInterface $request, PipelineContext $context, mixed $data): mixed
    {
        if ($data === null || !$request instanceof AbstractRequest) {
            return $data;
        }

        $dtoType = $request->getResponseType();
        if ($dtoType === null) {
            return $data;
        }

        $context->hydrationSourceTransformed = true;
        try {
            return $this->hydrator->hydrate($data, $dtoType, $context, $this->dtoHydrators->resolve($request, $context->config));
        } catch (HydrationException $exception) {
            if ($exception->reason === 'custom_hydrator_type_mismatch' && $exception->path === '') {
                throw ResponseContractGuard::mismatch($request, $context->config, $dtoType, $exception->actual ?? 'object');
            }
            throw $exception;
        }
    }
}
