<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Request;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\VO\Http\ProviderResponse;

class RequestException extends SdkException
{
    public function __construct(
        string|Message $message,
        public readonly ?ProviderResponse $response,
        int $code = 0,
        ?SdkException $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this->messageDefinition() ?? $this->getMessage(), $this->response, $this->getCode(), $this);
    }
}
