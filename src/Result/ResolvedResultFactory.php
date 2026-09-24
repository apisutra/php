<?php

declare(strict_types=1);

namespace ApiSutra\Result;

use ApiSutra\VO\Errors\ClientErrorFactory;
use ApiSutra\VO\Errors\ClientErrorMapperInterface;
use ApiSutra\VO\Errors\DefaultClientErrorMapper;
use ApiSutra\VO\Errors\ErrorContextFactoryInterface;

/**
 * Дефолтная фабрика результата.
 *
 * Инвариант:
 * - фабрика создаёт ResolvedResult с уже "замороженными" зависимостями
 *   (error mapper/context factory/continuation extractor) из ClientConfig.
 *
 * @see docs/guides/client-config/responses-errors.md
 * @see docs/guides/errors.md
 */
final class ResolvedResultFactory implements ResolvedResultFactoryInterface
{
    private readonly ClientErrorFactory $errorFactory;
    private readonly ?ErrorContextFactoryInterface $errorContextFactory;
    private readonly ?ContinuationTokenExtractorInterface $continuationTokenExtractor;

    public function __construct(
        ?ClientErrorMapperInterface $mapper = null,
        ?ErrorContextFactoryInterface $errorContextFactory = null,
        ?ContinuationTokenExtractorInterface $continuationTokenExtractor = null,
    ) {
        $this->errorFactory = new ClientErrorFactory(
            $mapper ?? new DefaultClientErrorMapper(),
        );
        $this->errorContextFactory = $errorContextFactory;
        $this->continuationTokenExtractor = $continuationTokenExtractor;
    }

    public function make(ExecutionResult $result): ResolvedResultInterface
    {
        return new ResolvedResult(
            result: $result,
            errorFactory: $this->errorFactory,
            errorContextFactory: $this->errorContextFactory,
            continuationTokenExtractor: $this->continuationTokenExtractor,
        );
    }
}
