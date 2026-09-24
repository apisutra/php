<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Configuration;

use ApiSutra\Result\ExecutionResult;
use ApiSutra\Localization\Message;
use Throwable;

final class ExceptionFactoryException extends ConfigurationException
{
    public readonly string $reason;

    public function __construct(public readonly ExecutionResult $result, Throwable $previous)
    {
        $this->reason = 'exception_factory_failed';
        parent::__construct(new Message('result.exception_factory_failed'), previous: $previous);
    }

    protected function copyForLocalization(): static
    {
        return new self($this->result, $this->getPrevious());
    }
}
