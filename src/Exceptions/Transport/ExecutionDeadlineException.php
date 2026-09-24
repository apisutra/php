<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Transport;

use ApiSutra\Localization\Message;
use ApiSutra\VO\Http\ProviderResponse;
use ApiSutra\Enums\Http\TransmissionState;
use Throwable;

final class ExecutionDeadlineException extends TimeoutException
{
    public function __construct(
        public readonly string $stage,
        ?Throwable $previous = null,
        public readonly ?ProviderResponse $response = null,
        public readonly ?int $bytesWritten = null,
        public readonly bool $partial = false,
        TransmissionState $transmissionState = TransmissionState::Unknown,
    ) {
        parent::__construct(new Message('errors.execution_budget_exhausted', ['stage' => $stage]), 0, $previous, $transmissionState);
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this->stage, $this, $this->response, $this->bytesWritten, $this->partial, $this->transmissionState);
    }
}
