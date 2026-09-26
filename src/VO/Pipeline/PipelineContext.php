<?php

declare(strict_types=1);

namespace ApiSutra\VO\Pipeline;

use ApiSutra\Result\ExecutionResult;
use ApiSutra\Http\RequestDestination;
use ApiSutra\VO\Files\FileTransferOptions;
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;
use ApiSutra\Contracts\Interfaces\Execution\ClientExecutorInterface;
use ApiSutra\Enums\Errors\ErrorCode;
use ApiSutra\Enums\Http\TransmissionState;
use ApiSutra\Enums\Execution\RequestRole;
use ApiSutra\Pipeline\Cache\CacheExecutionState;
use ApiSutra\Pipeline\Transport\CooldownDiagnostics;
use ApiSutra\Request\PaginationOptions;
use ApiSutra\Request\RequestOptions;
use ApiSutra\Request\RequestPaginationHelper;
use ApiSutra\Timing\ExecutionBudget;
use ApiSutra\VO\Http\PreparedRequest;
use ApiSutra\VO\Http\ProviderResponse;
use Throwable;
use ApiSutra\Diagnostics\ExecutionTrace;
use ApiSutra\Pipeline\Diagnostics\ExecutionScope;

class PipelineContext
{
    /** BeforeHydrate создал границу между HTTP-документом и входом гидратора. */
    public readonly ExecutionTrace $trace;
    public ?ExecutionScope $scope = null;
    /** Служебный helper принадлежит этому исполнению, а не общему request. */
    public ?RequestPaginationHelper $requestPaginationHelper = null;
    /** Внешний исполнитель сохраняется только на время текущего вызова. */
    public ?ClientExecutorInterface $executor = null;
    /** Клиент активного выполнения; не является постоянной привязкой request. */
    public ?ClientInterface $client = null;
    /** @var list<ExecutionResult> */
    public array $nested = [];
    public bool $hydrationSourceTransformed = false;
    /** @internal Фактическая замена данных, отдельно от диагностической границы hooks. */
    public bool $hydrationInputReplaced = false;
    public ?CacheExecutionState $cacheExecution = null;
    public ?RequestDestination $destination = null;
    public ?FileTransferOptions $fileTransfer = null;
    public ?ErrorCode $failureCode = null;
    /** Исключение этого исполнения: связывает диагностику с доставкой в batch/pool. */
    public ?Throwable $failureException = null;
    public ?string $retryRefusalReason = null;
    public ?ExecutionBudget $budget = null;
    public ?CooldownDiagnostics $cooldownDiagnostics = null;
    public ?ProviderResponse $lastResponse = null;
    /** Накопленный исход всех отправок этого запроса, без auth и дочерних запросов. */
    public ?string $authTokenVersion = null;
    public ?string $sentAuthTokenVersion = null;

    public TransmissionState $transmissionState = TransmissionState::NotSent;

    public function __construct(
        public readonly RequestInterface $request,
        public readonly ClientConfig $config,
        public readonly string $traceId,
        public readonly RequestRole $role = RequestRole::Root,
        public readonly ?PipelineContext $parent = null,
        public readonly ?RequestOptions $options = null,
        public readonly ?PaginationOptions $paginationOptions = null,
        /**
         * @var array<string, mixed>|null
         */
        public ?array $requestContractDebug = null,
        public ?PreparedRequest $preparedRequest = null,
        public ?ProviderResponse $response = null,
        public ?object $dto = null,
        ?ExecutionTrace $trace = null,
    ) {
        $this->trace = $trace ?? ExecutionTrace::create($traceId, $parent?->trace);
    }

    /**
     * Создать дочерний контекст (для nested/dependency)
     */
    public function child(RequestInterface $request, RequestRole $role): self
    {
        return new self(
            request: $request,
            config: $this->config,
            traceId: $this->traceId,
            role: $role,
            parent: $this,
            options: null,
            paginationOptions: null,
        );
    }
}
