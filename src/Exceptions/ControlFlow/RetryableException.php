<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\ControlFlow;

use ApiSutra\Localization\Message;
use Exception;

class RetryableException extends ControlFlowException
{
    public function __construct(
        string|Message $message = new Message('errors.request_retry_required'),
        public readonly ?int $retryAfter = null,
        public readonly ?int $maxAttempts = null,
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this->messageDefinition() ?? $this->getMessage(), $this->retryAfter, $this->maxAttempts, $this->getCode(), $this);
    }
}
