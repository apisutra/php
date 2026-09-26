<?php

declare(strict_types=1);

namespace ApiSutra\Continuation;

use ApiSutra\Enums\Continuation\ContinuationMode;

final readonly class ContinuationContext
{
    public function __construct(
        public ?string $finalType,
        public ?string $unwrap,
        public ?string $sourceRequestClass,
        public ContinuationMode $mode,
        /** @internal Режим клиента; resolver не включает сохранение формы самостоятельно. */
        public bool $jsonShapeValidation = true,
    ) {
    }
}
