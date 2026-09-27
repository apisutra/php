<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Enums\DataTransfer\DiscriminatorMode;
use ApiSutra\Enums\DataTransfer\UnknownVariant;
use ApiSutra\Serialization\Variants\VariantDefinition;

final readonly class HydrationRules
{
    /**
     * @param array<class-string, DtoRules> $definitions
     * @param array<class-string, VariantDefinition> $variants
     */
    private function __construct(private RulePolicy $policy, private array $definitions = [], private array $variants = [])
    {
    }

    public static function create(?RulePolicy $defaults = null): self
    {
        return new self($defaults ?? new RulePolicy());
    }

    public function withDto(string $class, DtoRules $rules): self
    {
        if (array_key_exists($class, $this->definitions)) {
            throw new ConfigurationException(new Message('serialization.class_rules_are_already_defined', ['class' => $class]));
        }
        return new self($this->policy, $this->definitions + [$class => $rules], $this->variants);
    }

    /** @param array<array-key, class-string> $map */
    public function withVariants(
        string $type,
        string $discriminator,
        array $map,
        DiscriminatorMode $mode = DiscriminatorMode::Value,
        UnknownVariant|string $unknown = UnknownVariant::Error,
    ): self {
        if (isset($this->variants[$type])) {
            throw new ConfigurationException(new Message('serialization.duplicate_dto_variants', ['type' => $type]));
        }
        return new self($this->policy, $this->definitions, $this->variants + [
            $type => new VariantDefinition($discriminator, $map, $mode, $unknown),
        ]);
    }

    /** @internal */
    public function variantsFor(string $type): ?VariantDefinition
    {
        return $this->variants[$type] ?? null;
    }

    public function defaults(): RulePolicy
    {
        return $this->policy;
    }

    public function rulesFor(string $class): ?DtoRules
    {
        return $this->definitions[$class] ?? null;
    }

    /**
     * @internal
     * @return array<class-string, DtoRules>
     */
    public function definitions(): array
    {
        return $this->definitions;
    }
}
