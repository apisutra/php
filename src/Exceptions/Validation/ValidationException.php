<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Validation;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Core\SdkException;
use ApiSutra\VO\Errors\ValidationError;
use Exception;

class ValidationException extends SdkException
{
    /**
     * @param array<ValidationError> $errors
     */
    public function __construct(
        public readonly array $errors,
        string|Message $message = new Message('errors.validation_failed'),
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
    protected function copyForLocalization(): static
    {
        return new ($this::class)($this->errors, $this->messageDefinition() ?? $this->getMessage(), $this->getCode(), $this);
    }
}
