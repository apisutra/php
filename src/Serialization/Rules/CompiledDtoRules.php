<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ReflectionClass;

/** @internal Описание класса для одного набора; не содержит состояния гидратации. */
final readonly class CompiledDtoRules
{
    /** @param array<string, string> $origins */
    public function __construct(
        public ReflectionClass $reflection,
        public ?DtoRules $declaration,
        public RulePolicy $policy,
        public bool $legacyProfile,
        public bool $enhanced = false,
        public array $origins = [],
    ) {
    }

    public function field(string $property): ?FieldRule
    {
        return $this->declaration?->fields[$property] ?? null;
    }

    public function policyFor(string $property): RulePolicy
    {
        return $this->field($property)?->policy?->over($this->policy) ?? $this->policy;
    }
}
