<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Flow;

final readonly class RequestContractFieldResolution
{
    /**
     * @param array<string, mixed>|null $violation
     */
    public function __construct(
        public ?RequestContractFieldValue $field,
        public ?array $violation = null,
    ) {
    }

    public function failed(): bool
    {
        return $this->violation !== null;
    }
}
