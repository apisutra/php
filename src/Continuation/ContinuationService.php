<?php

declare(strict_types=1);

namespace ApiSutra\Continuation;

use ApiSutra\Timing\CooperativeSleeper;
use ApiSutra\Contracts\Interfaces\Localization\LocalizableExceptionInterface;
use ApiSutra\Localization\Message;
use ApiSutra\Attributes\Response\ContinuationResult;
use ApiSutra\Contracts\Interfaces\Continuation\ContinuationStateResolverInterface;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestExecutionInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestOptionsProviderInterface;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Enums\Continuation\ContinuationMode;
use ApiSutra\Enums\Continuation\ContinuationStatus;
use ApiSutra\Exceptions\Configuration\ContinuationConfigurationException;
use ApiSutra\Exceptions\Continuation\ContinuationAwaitException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Execution\ExecutionDispatch;
use ApiSutra\Exceptions\ControlFlow\ExecutorContractViolation;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Pagination\PaginationRule;
use ApiSutra\Request\RequestSpecResolver;
use ApiSutra\Result\ExecutionResult;
use ApiSutra\Result\ResultExceptionSelector;
use ApiSutra\Serialization\Hydrator;
use ApiSutra\Serialization\Rules\SourceLocation;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;
use ApiSutra\Collections\ErrorCollection;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Enums\Result\ResultStatus;
use ApiSutra\Localization\ExceptionLocalization;
use ApiSutra\Pipeline\Diagnostics\ExecutionEnvelope;
use ApiSutra\Pipeline\Diagnostics\ExecutionScope;
use Throwable;
use ApiSutra\Support\ContainerProviderRegistry;

/**
 * Исполняет ожидание по явному критерию протокола и гидратирует только Ready.
 * Ошибки гидратации и resolver никогда не превращаются в Pending.
 *
 * @see docs/guides/provider-async-await.md
 */
final readonly class ContinuationService
{
    public function __construct(
        private ClientInterface $client,
        private Hydrator $hydrator,
    ) {
    }

    public function awaitFromStartResult(
        ExecutionResult $startResult,
        ?RequestInterface $sourceRequest = null,
        ?string $finalTypeOverride = null,
        ?ContinuationAwaitOptions $options = null,
    ): mixed {
        try {
            return $this->resolveFromStartResult($startResult, $sourceRequest, $finalTypeOverride, $options)->value;
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->client->getConfig()->localization);
        }
    }

    public function resolveFromStartResult(
        ExecutionResult $startResult,
        ?RequestInterface $sourceRequest = null,
        ?string $finalTypeOverride = null,
        ?ContinuationAwaitOptions $options = null,
    ): ContinuationOutcome {
        $scope = $this->scope($this->resolveSourceRequestClass($sourceRequest), $startResult->trace, $startResult->traceId);
        return $this->runScoped($scope, fn (): ContinuationOutcome|AwaitFailure => $this->resolveScoped($scope, $startResult, $sourceRequest, $finalTypeOverride, $options));
    }

    public function awaitByToken(string $token, string $sourceRequestClass, ?ContinuationAwaitOptions $options = null): mixed
    {
        $scope = $this->scope($sourceRequestClass);
        return $this->runScoped($scope, fn (): ContinuationOutcome|AwaitFailure => $this->awaitByTokenScoped($scope, $token, $sourceRequestClass, $options))->value;
    }

    public function awaitByTokenAs(string $token, string $finalType, ?ContinuationAwaitOptions $options = null): mixed
    {
        $scope = $this->scope();
        return $this->runScoped($scope, fn (): ContinuationOutcome|AwaitFailure => $this->awaitByTokenAsScoped($scope, $token, $finalType, $options))->value;
    }

    public function hydrateOutcome(ContinuationOutcome $outcome, string $finalType): ContinuationOutcome
    {
        $scope = $this->scope($outcome->lastResult->requestClass, $outcome->trace, $outcome->trace->traceId ?? $outcome->lastResult->traceId);
        return $this->runScoped($scope, fn (): ContinuationOutcome => $this->hydrateScoped($scope, $outcome, $finalType));
    }

    private function scope(?string $requestClass = null, ?ExecutionTrace $parent = null, ?string $traceId = null): ExecutionScope
    {
        return $this->client->execution()->createScope($requestClass, $parent, $traceId);
    }

    /** @param callable(): (ContinuationOutcome|AwaitFailure) $execute */
    private function runScoped(ExecutionScope $scope, callable $execute): ContinuationOutcome
    {
        try {
            $scope->start();
            try {
                $outcome = ContainerProviderRegistry::withProvider(ContainerProviderRegistry::resolve($this->client->getConfig()->containerProvider), $execute(...));
            } catch (Throwable $exception) {
                $exception = $exception instanceof ExecutorContractViolation
                    ? $exception->cause
                    : ExceptionLocalization::apply($exception, $this->client->getConfig()->localization);
                $scope->finish(
                    new ExecutionResult(null, ResultStatus::FAILED, new ErrorCollection([]), exception: $exception),
                    fn (): array => $exception instanceof ContinuationAwaitException ? $exception->logContext() : [],
                );
                throw $exception;
            }
            if ($outcome instanceof AwaitFailure) {
                $failed = $outcome->result;
                $scope->finish(new ExecutionResult(null, ResultStatus::FAILED, $failed->errors, exception: $failed->exception, response: $failed->response, nested: [$failed]));
                throw ResultExceptionSelector::select($failed);
            }
            $result = $scope->finish(new ExecutionResult(null, ResultStatus::SUCCESS, new ErrorCollection([])));
            return new ContinuationOutcome($outcome->value, $outcome->payload, $outcome->path, $outcome->lastResult, $outcome->attempts, $scope->trace, $result->audit, $outcome->input());
        } finally {
            $scope->release();
        }
    }

    private function resolveScoped(
        ExecutionScope $scope,
        ExecutionResult $startResult,
        ?RequestInterface $sourceRequest = null,
        ?string $finalTypeOverride = null,
        ?ContinuationAwaitOptions $options = null,
    ): ContinuationOutcome|AwaitFailure {
        try {
            $sourceClass = $this->resolveSourceRequestClass($sourceRequest);
            $declaration = $this->resolveContinuationResult($sourceClass);
            $context = new ContinuationContext(
                finalType: $this->normalizeFinalType($finalTypeOverride ?? $declaration?->finalType),
                unwrap: $declaration?->unwrap,
                sourceRequestClass: $sourceClass,
                mode: $this->resolveMode($sourceRequest, $declaration),
                jsonShapeValidation: $this->client->getConfig()->hydration->jsonShapeValidation ?? true,
            );
            $resolver = $this->resolveStateResolver($declaration, $context);
            $attempts = 0;
            if ($context->mode !== ContinuationMode::Async) {
                $attempts++;
                $outcome = $this->evaluate($scope, $startResult, $context, $resolver, $attempts);
                if ($outcome !== null) {
                    return $outcome;
                }
                if ($context->mode === ContinuationMode::Sync) {
                    throw $this->awaitError($scope, 'final_not_ready', $startResult, $attempts);
                }
            }

            $token = $this->requireToken($scope, $startResult, $attempts);
            if ($token instanceof AwaitFailure) {
                return $token;
            }
            return $this->awaitByTokenInternal(
                $scope,
                $token,
                $context,
                $resolver,
                $declaration->pollRequest ?? $this->client->getConfig()->defaultPollRequest,
                $options ?? new ContinuationAwaitOptions(),
                $attempts,
            );
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->client->getConfig()->localization);
        }
    }

    private function awaitByTokenScoped(
        ExecutionScope $scope,
        string $token,
        string $sourceRequestClass,
        ?ContinuationAwaitOptions $options = null,
    ): ContinuationOutcome|AwaitFailure {
        try {
            $sourceClass = trim($sourceRequestClass);
            if ($sourceClass === '') {
                throw new ContinuationConfigurationException(new Message('continuation.sourcerequestclass_must_not_be_empty'));
            }
            $declaration = $this->resolveContinuationResult($sourceClass);
            if ($declaration === null) {
                throw new ContinuationConfigurationException(
                    new Message('continuation.continuationresult_is_not_declared_for_sourcerequestclass', ['sourceClass' => $sourceClass]),
                );
            }
            $context = new ContinuationContext(
                $this->normalizeFinalType($declaration->finalType),
                $declaration->unwrap,
                $sourceClass,
                ContinuationMode::Async,
                $this->client->getConfig()->hydration->jsonShapeValidation ?? true,
            );
            return $this->awaitByTokenInternal(
                $scope,
                $token,
                $context,
                $this->resolveStateResolver($declaration, $context),
                $declaration->pollRequest ?? $this->client->getConfig()->defaultPollRequest,
                $options ?? new ContinuationAwaitOptions(),
            );
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->client->getConfig()->localization);
        }
    }

    private function awaitByTokenAsScoped(
        ExecutionScope $scope,
        string $token,
        string $finalType,
        ?ContinuationAwaitOptions $options = null,
    ): ContinuationOutcome|AwaitFailure {
        try {
            $context = new ContinuationContext(
                $this->normalizeFinalType($finalType),
                null,
                null,
                ContinuationMode::Async,
                $this->client->getConfig()->hydration->jsonShapeValidation ?? true,
            );
            return $this->awaitByTokenInternal(
                $scope,
                $token,
                $context,
                $this->resolveStateResolver(null, $context),
                $this->client->getConfig()->defaultPollRequest,
                $options ?? new ContinuationAwaitOptions(),
            );
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->client->getConfig()->localization);
        }
    }

    private function hydrateScoped(ExecutionScope $scope, ContinuationOutcome $outcome, string $finalType): ContinuationOutcome
    {
        try {
            $type = $this->normalizeFinalType($finalType);
            $shapeError = !is_array($outcome->payload) && !is_object($outcome->payload);
            try {
                if ($shapeError) {
                    throw HydrationException::invalidValue(
                        'unexpected_response_shape',
                        $type,
                        get_debug_type($outcome->payload),
                    );
                }
                $value = $this->hydrator->hydrateInput($outcome->input(), $type);
            } catch (HydrationException $exception) {
                if ($exception->sourcePathKind !== null || $this->hydrator->tracksSource($type)) {
                    if ($outcome->path === null) {
                        $exception = $exception->withSource(new SourceLocation(kind: SourcePathKind::Unavailable));
                    } elseif ($exception->sourcePathKind === null) {
                        $exception = $exception->withSource(new SourceLocation());
                    }
                }
                $prefix = $outcome->path ?? ($shapeError ? '$' : '');
                if ($prefix !== '') {
                    $exception = $exception->prependSourcePath($prefix)->prependPath($prefix);
                }
                throw $this->awaitError(
                    $scope,
                    'final_hydration_failed',
                    $outcome->lastResult,
                    $outcome->attempts,
                    $exception,
                );
            }

            return new ContinuationOutcome(
                $value,
                $outcome->payload,
                $outcome->path,
                $outcome->lastResult,
                $outcome->attempts,
                input: $outcome->input(),
            );
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->client->getConfig()->localization);
        }
    }

    private function awaitByTokenInternal(
        ExecutionScope $scope,
        string $token,
        ContinuationContext $context,
        ContinuationStateResolverInterface $resolver,
        ?string $pollRequestClass,
        ContinuationAwaitOptions $options,
        int $attempts = 0,
    ): ContinuationOutcome|AwaitFailure {
        if ($pollRequestClass === null || trim($pollRequestClass) === '') {
            throw new ContinuationConfigurationException(
                new Message('continuation.poll_request_is_not_specified_set_continuationresult_pollrequest_or'),
            );
        }
        $currentToken = trim($token);
        if ($currentToken === '') {
            throw new ContinuationConfigurationException(new Message('continuation.continuation_token_must_not_be_empty'));
        }

        for ($poll = 1; $poll <= $options->maxAttempts; $poll++) {
            $result = $this->sendPollRequest($scope, $pollRequestClass, $currentToken);
            $attempts++;
            $outcome = $this->evaluate($scope, $result, $context, $resolver, $attempts);
            if ($outcome !== null) {
                return $outcome;
            }
            $currentToken = $this->requireToken($scope, $result, $attempts);
            if ($currentToken instanceof AwaitFailure) {
                return $currentToken;
            }
            if ($poll === $options->maxAttempts) {
                throw $this->awaitError($scope, 'attempts_exhausted', $result, $attempts);
            }
            if ($options->intervalMs > 0) {
                (new CooperativeSleeper())->sleepMs($options->intervalMs);
            }
        }

        // ContinuationAwaitOptions запрещает нулевой лимит.
        throw new ContinuationConfigurationException(new Message('continuation.polling_limit_must_be_positive'));
    }

    private function evaluate(
        ExecutionScope $scope,
        ExecutionResult $result,
        ContinuationContext $context,
        ContinuationStateResolverInterface $resolver,
        int $attempts,
    ): ContinuationOutcome|AwaitFailure|null {
        $state = $resolver->resolve($result, $context);
        if ($state->status === ContinuationStatus::Pending) {
            return null;
        }
        if ($state->status === ContinuationStatus::Failed) {
            if ($result->isFailed()) {
                return new AwaitFailure($result);
            }
            throw $this->awaitError($scope, 'continuation_failed', $result, $attempts);
        }
        $outcome = new ContinuationOutcome($state->payload, $state->payload, $state->path, $result, $attempts, input: $state->input());

        return $context->finalType === null ? $outcome : $this->hydrateScoped($scope, $outcome, $context->finalType);
    }

    private function awaitError(
        ExecutionScope $scope,
        string $reason,
        ExecutionResult $result,
        int $attempts,
        ?HydrationException $previous = null,
    ): ContinuationAwaitException {
        $message = match ($reason) {
            'final_hydration_failed' => new Message('continuation.failed_to_hydrate_the_ready_continuation_result'),
            'final_not_ready' => new Message('continuation.final_result_is_not_ready_in_sync_mode'),
            'continuation_token_missing' => new Message('continuation.await_did_not_receive_a_continuation_token'),
            'attempts_exhausted' => new Message('continuation.polling_request_limit_exhausted'),
            default => new Message('continuation.continuation_protocol_reported_a_failure'),
        };
        $exception = new ContinuationAwaitException($message, $reason, $attempts, $result, $previous, $scope->trace);
        return $exception;
    }

    private function requireToken(ExecutionScope $scope, ExecutionResult $result, int $attempts): string|AwaitFailure
    {
        $extractor = $this->client->getConfig()->continuationTokenExtractor;
        if ($extractor === null) {
            throw new ContinuationConfigurationException(
                new Message('continuation.continuationtokenextractor_is_required_to_continue_awaiting'),
            );
        }
        $token = $extractor->extract($result);
        if ($token !== null && trim($token) !== '') {
            return trim($token);
        }
        if ($result->isFailed()) {
            return new AwaitFailure($result);
        }
        throw $this->awaitError($scope, 'continuation_token_missing', $result, $attempts);
    }

    private function resolveStateResolver(
        ?ContinuationResult $declaration,
        ContinuationContext $context,
    ): ContinuationStateResolverInterface {
        $class = $declaration?->stateResolver;
        if ($class !== null) {
            if (!is_subclass_of($class, ContinuationStateResolverInterface::class)) {
                throw new ContinuationConfigurationException(new Message('continuation.invalid_stateresolver_class', ['class' => $class]));
            }
            $reflection = new ReflectionClass($class);
            $requiredParameters = $reflection->getConstructor()?->getNumberOfRequiredParameters() ?? 0;
            if (!$reflection->isInstantiable() || $requiredParameters > 0) {
                throw new ContinuationConfigurationException(
                    new Message('continuation.stateresolver_must_be_constructible_without_arguments', ['class' => $class]),
                );
            }
            return new $class();
        }
        if ($context->unwrap !== null && trim($context->unwrap) !== '') {
            return new FinalPathStateResolver();
        }
        return $this->client->getConfig()->continuationStateResolver
            ?? throw new ContinuationConfigurationException(
                new Message('continuation.await_requires_unwrap_or_continuationstateresolver'),
            );
    }

    private function normalizeFinalType(?string $type): ?string
    {
        if ($type === null) {
            return null;
        }
        $type = trim($type);
        if ($type === '' || !class_exists($type)) {
            throw new ContinuationConfigurationException(new Message('continuation.final_dto_class_not_found', ['type' => $type]));
        }
        return $type;
    }

    private function sendPollRequest(ExecutionScope $scope, string $pollRequestClass, string $token): ExecutionResult
    {
        $request = $this->instantiatePollRequest($pollRequestClass, $token);
        if ($request instanceof AbstractRequest) {
            $request->setClient($this->client);
        }

        $options = $request instanceof RequestOptionsProviderInterface ? $request->getOptions() : RequestOptions::empty();
        $single = new ExecutionEnvelope($request, options: $options->withPaginationRule(PaginationRule::single()));
        return ExecutionDispatch::execute($this->client->execution(), $single, RequestRole::Root, parentTrace: $scope->trace);
    }

    private function instantiatePollRequest(string $pollRequestClass, string $token): RequestInterface
    {
        $class = trim($pollRequestClass);
        if ($class === '') {
            throw new ContinuationConfigurationException(new Message('continuation.pollrequestclass_must_not_be_empty'));
        }

        if (!class_exists($class)) {
            throw new ContinuationConfigurationException(new Message('continuation.poll_request_class_not_found', ['class' => $class]));
        }

        if (!is_subclass_of($class, RequestInterface::class)) {
            throw new ContinuationConfigurationException(
                new Message('continuation.poll_request_class_must_implement_requestinterface', ['class' => $class]),
            );
        }

        $reflection = new ReflectionClass($class);
        $constructor = $reflection->getConstructor();
        if ($constructor === null) {
            throw new ContinuationConfigurationException(
                new Message('continuation.poll_request_constructor_must_require_a_token_parameter', ['class' => $class]),
            );
        }

        $required = array_values(array_filter(
            $constructor->getParameters(),
            static fn (ReflectionParameter $parameter): bool => !$parameter->isOptional(),
        ));

        if (count($required) !== 1) {
            throw new ContinuationConfigurationException(
                new Message('continuation.poll_request_constructor_must_have_exactly_one_required_parameter', ['class' => $class]),
            );
        }

        $tokenParameter = $required[0];
        $tokenValue = $this->castTokenForParameter($token, $tokenParameter);

        $args = [];
        foreach ($constructor->getParameters() as $parameter) {
            if ($parameter->getName() === $tokenParameter->getName()) {
                $args[] = $tokenValue;
                continue;
            }

            if ($parameter->isDefaultValueAvailable()) {
                $args[] = $parameter->getDefaultValue();
                continue;
            }

            throw new ContinuationConfigurationException(
                new Message('continuation.failed_to_prepare_poll_request_constructor_arguments', ['class' => $class]),
            );
        }

        $instance = $reflection->newInstanceArgs($args);
        if (!$instance instanceof RequestInterface) {
            throw new ContinuationConfigurationException(
                new Message('continuation.poll_request_class_must_implement_requestinterface', ['class' => $class]),
            );
        }

        return $instance;
    }

    private function castTokenForParameter(string $token, ReflectionParameter $parameter): string|int|float|bool
    {
        $type = $parameter->getType();
        if (!$type instanceof ReflectionNamedType || !$type->isBuiltin()) {
            throw new ContinuationConfigurationException(
                new Message('continuation.required_token_parameter_must_have_a_scalar_type', ['value0' => $parameter->getName()]),
            );
        }

        return match ($type->getName()) {
            'string' => $token,
            'int' => $this->castTokenToInt($token, $parameter->getName()),
            'float' => $this->castTokenToFloat($token, $parameter->getName()),
            'bool' => $this->castTokenToBool($token, $parameter->getName()),
            default => throw new ContinuationConfigurationException(
                new Message('continuation.required_token_parameter_must_have_a_scalar_type', ['value0' => $parameter->getName()]),
            ),
        };
    }

    private function castTokenToInt(string $token, string $parameter): int
    {
        if (preg_match('/^-?\d+$/', $token) !== 1) {
            throw new ContinuationConfigurationException(
                new Message('continuation.failed_to_convert_token_to_int_for_parameter', ['parameter' => $parameter]),
            );
        }

        return (int) $token;
    }

    private function castTokenToFloat(string $token, string $parameter): float
    {
        if (!is_numeric($token)) {
            throw new ContinuationConfigurationException(
                new Message('continuation.failed_to_convert_token_to_float_for_parameter', ['parameter' => $parameter]),
            );
        }

        return (float) $token;
    }

    private function castTokenToBool(string $token, string $parameter): bool
    {
        $normalized = strtolower(trim($token));
        return match ($normalized) {
            '1', 'true', 'yes' => true,
            '0', 'false', 'no' => false,
            default => throw new ContinuationConfigurationException(
                new Message('continuation.failed_to_convert_token_to_bool_for_parameter', ['parameter' => $parameter]),
            ),
        };
    }

    private function resolveMode(?RequestInterface $sourceRequest, ?ContinuationResult $continuation): ContinuationMode
    {
        $modeOverride = $this->resolveContinuationModeOverride($sourceRequest);
        if ($modeOverride !== null) {
            return $modeOverride;
        }

        if ($continuation?->defaultMode !== null) {
            return $continuation->defaultMode;
        }

        return $this->client->getConfig()->defaultContinuationMode;
    }

    private function resolveContinuationModeOverride(?RequestInterface $sourceRequest): ?ContinuationMode
    {
        if ($sourceRequest instanceof RequestExecutionInterface) {
            return $sourceRequest->getOptions()->getContinuationModeOverride();
        }

        if ($sourceRequest instanceof RequestOptionsProviderInterface) {
            return $sourceRequest->getOptions()->getContinuationModeOverride();
        }

        return null;
    }

    private function resolveSourceRequestClass(?RequestInterface $sourceRequest): ?string
    {
        if ($sourceRequest instanceof RequestExecutionInterface) {
            return $sourceRequest->getRequest()::class;
        }

        if ($sourceRequest === null) {
            return null;
        }

        return $sourceRequest::class;
    }

    private function resolveContinuationResult(?string $sourceRequestClass): ?ContinuationResult
    {
        if ($sourceRequestClass === null || trim($sourceRequestClass) === '') {
            return null;
        }

        if (!class_exists($sourceRequestClass)) {
            throw new ContinuationConfigurationException(new Message('continuation.source_request_class_not_found', ['sourceRequestClass' => $sourceRequestClass]));
        }

        $spec = (new RequestSpecResolver())->resolveClass($sourceRequestClass);

        return $spec->continuationResult;
    }
}
