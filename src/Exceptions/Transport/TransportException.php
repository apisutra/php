<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Transport;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\Enums\Http\TransmissionState;
use Throwable;

class TransportException extends SdkException
{
    public function __construct(
        string|Message $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly TransmissionState $transmissionState = TransmissionState::Unknown,
    ) {
        parent::__construct($message, $code, $previous);
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this->messageDefinition() ?? $this->getMessage(), $this->getCode(), $this, $this->transmissionState);
    }
}
