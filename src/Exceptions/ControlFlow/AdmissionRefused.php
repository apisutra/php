<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\ControlFlow;

use ApiSutra\Exceptions\Request\RateLimitException;
use ApiSutra\Execution\Admission\AdmissionScope;

/** Управляющий отказ области, не пользовательское исключение результата. */
final class AdmissionRefused extends ControlFlowException
{
    public function __construct(
        public readonly AdmissionScope $scope,
        public readonly string $reason,
        public readonly RateLimitException $cause,
    ) {
        parent::__construct($cause->messageDefinition() ?? $reason, previous: $cause);
    }
    protected function copyForLocalization(): static
    {
        return new self($this->scope, $this->reason, $this->cause);
    }
}
