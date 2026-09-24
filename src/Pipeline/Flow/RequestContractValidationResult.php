<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Flow;

final readonly class RequestContractValidationResult
{
    /**
     * @param array<string, mixed>|null $oneOfDebug
     */
    public function __construct(
        public ?RequestContractViolation $violation,
        public ?array $oneOfDebug = null,
    ) {
    }

    public function failed(): bool
    {
        return $this->violation !== null;
    }
}
