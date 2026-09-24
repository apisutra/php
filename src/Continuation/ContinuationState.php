<?php

declare(strict_types=1);

namespace ApiSutra\Continuation;

use ApiSutra\Enums\Continuation\ContinuationStatus;

final readonly class ContinuationState
{
    private function __construct(
        public ContinuationStatus $status,
        public mixed $payload = null,
        public ?string $path = null,
    ) {
    }

    public static function pending(): self
    {
        return new self(ContinuationStatus::Pending);
    }

    public static function ready(mixed $payload, ?string $path = null): self
    {
        return new self(ContinuationStatus::Ready, $payload, $path);
    }

    public static function failed(): self
    {
        return new self(ContinuationStatus::Failed);
    }
}
