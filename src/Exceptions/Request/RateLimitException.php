<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Request;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\VO\Http\ProviderResponse;

class RateLimitException extends ClientException
{
    public function __construct(
        string|Message $message,
        ?ProviderResponse $response,
        public readonly ?int $retryAfter = null,
        int $code = 0,
        ?SdkException $previous = null,
        public readonly ?ProviderResponse $lastResponse = null,
    ) {
        parent::__construct($message, $response, $code, $previous);
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this->messageDefinition() ?? $this->getMessage(), $this->response, $this->retryAfter, $this->getCode(), $this, $this->lastResponse);
    }
}
