<?php

declare(strict_types=1);

namespace ApiSutra\Exceptions\Serialization;

use ApiSutra\Localization\Message;

final class ResponseTypeMismatchException extends HydrationException
{
    public function __construct(string|Message $message, string $expected, string $actual)
    {
        parent::__construct(
            message: $message,
            reason: 'response_type_mismatch',
            path: '$',
            expected: $expected,
            actual: $actual,
        );
    }

    protected function copyForLocalization(): static
    {
        return new self($this->messageDefinition() ?? $this->getMessage(), $this->expected, $this->actual);
    }
}
