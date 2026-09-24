<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\ControlFlow;

use ApiSutra\Localization\Message;
use Exception;

class EarlyReturnException extends ControlFlowException
{
    public function __construct(
        public readonly mixed $data,
        string|Message $message = new Message('errors.return_without_http'),
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this->data, $this->messageDefinition() ?? $this->getMessage(), $this->getCode(), $this);
    }
}
