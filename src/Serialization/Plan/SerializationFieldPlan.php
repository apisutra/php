<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Plan;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\DateTimeTo;
use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Enums\Configuration\NamingStrategy;

/** @internal Рецепт чтения и имени; null/receiver пропускаются исполнителем по текущей policy. */
final readonly class SerializationFieldPlan
{
    private string $snakeName;

    public function __construct(
        public string $name,
        public SerializationValuePlan $value,
        private ?string $outputName,
        public ?DateTimeTo $dateTimeTo,
    ) {
        $this->snakeName = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name);
    }

    public function outputName(DtoSerializationPolicy $policy): string
    {
        return $this->outputName ?? match ($policy->namingStrategy) {
            NamingStrategy::SnakeCase => $this->snakeName,
            NamingStrategy::None => $this->name,
        };
    }

    public function withCast(?Cast $cast): self
    {
        return new self(
            $this->name,
            new SerializationValuePlan($this->value->property, $cast),
            $this->outputName,
            $this->dateTimeTo,
        );
    }
}
