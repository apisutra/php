<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Serialization;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Core\SdkException;
use Throwable;

class ResponseDecodingException extends SdkException
{
    public function __construct(
        string|Message $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        public readonly ?string $reason = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this->messageDefinition() ?? $this->getMessage(), $this->getCode(), $this, $this->reason);
    }
}
