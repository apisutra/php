<?php

declare(strict_types=1);

namespace ApiSutra\Continuation;

use ApiSutra\Enums\Continuation\ContinuationStatus;
use ApiSutra\Serialization\Input\HydrationInput;

final readonly class ContinuationState
{
    private function __construct(
        public ContinuationStatus $status,
        public mixed $payload = null,
        public ?string $path = null,
        private ?HydrationInput $input = null,
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

    /** @internal Штатный resolver сохраняет только выбранное поддерево JSON. */
    public static function readyInput(HydrationInput $input, string $path): self
    {
        return new self(ContinuationStatus::Ready, $input->value, $path, $input);
    }

    /** @internal */
    public function input(): HydrationInput
    {
        return $this->input ?? new HydrationInput($this->payload);
    }
}
