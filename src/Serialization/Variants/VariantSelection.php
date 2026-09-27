<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Variants;

/** @internal Результат выбора одного узла, никогда не кешируется с декларацией. */
final readonly class VariantSelection
{
    /**
     * @param class-string|null $class
     * @param list<array-key> $segments
     */
    public function __construct(
        public ?string $class,
        public mixed $payload,
        public array $segments = [],
        public bool $skip = false,
    ) {
    }
}
