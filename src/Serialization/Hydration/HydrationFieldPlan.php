<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Hydration;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Attributes\DataTransfer\EmptyStringAsNull;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Config\DtoHydrationPolicy;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Enums\DataTransfer\EmptyStringBehavior;
use ApiSutra\Serialization\Rules\DefaultSpec;
use ApiSutra\Serialization\Rules\FieldRule;
use ApiSutra\Serialization\Rules\HandlerSpec;
use ApiSutra\Serialization\Rules\InputShape;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ValueShape;
use ReflectionParameter;
use ReflectionProperty;

/** @internal Нормализованные операции; правила источника выбираются до исполнения значений. */
final readonly class HydrationFieldPlan
{
    public string $name;
    private string $snakeName;
    private ?string $source;
    /** @var list<string> */
    public array $fallbacks;
    public ?Cast $cast;
    public ?Nested $nested;
    public ?DateTimeFrom $dateTimeFrom;
    public ?EmptyStringAsNull $emptyStringAsNull;
    public ?DefaultValue $default;
    public ?DefaultSpec $externalDefault;
    public ?HandlerSpec $handler;
    public ?ValueShape $shape;
    public ?InputShape $inputShape;
    public bool $normalizeKeys;
    public bool $collectionShape;
    public bool $required;
    public bool $forbidExplicitNull;
    public bool $constructorValue;
    public bool $constructorValueAllowMissing;
    public HydrationTransform $transform;
    public ?DtoHydrationPolicy $hydrationPolicy;

    /** @param array<string, object|null> $attributes */
    public function __construct(
        public ReflectionProperty $property,
        public ?ReflectionParameter $parameter,
        private ?FieldRule $rule,
        public ?RulePolicy $policy,
        private bool $legacyProfile,
        public string $origin,
        array $attributes,
    ) {
        $this->name = $property->getName();
        $this->snakeName = strtolower($this->name) === $this->name ? $this->name
            : strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $this->name) ?? $this->name);
        $this->cast = $attributes['cast'];
        $this->nested = $attributes['nested'];
        $this->dateTimeFrom = $attributes['dateTimeFrom'];
        $this->emptyStringAsNull = $attributes['emptyStringAsNull'];
        $this->default = $attributes['default'];
        $this->source = $rule->from ?? $this->nested->from
            ?? $attributes['from']->name ?? $attributes['map']->name ?? null;
        $fallbacks = $rule?->from !== null ? $rule->fallback : ($this->nested->fallback ?? []);
        $this->fallbacks = $rule?->from === null && $fallbacks === [] && $attributes['from'] !== null
            ? $attributes['from']->fallback : $fallbacks;
        $this->externalDefault = $rule?->default;
        $this->handler = $rule?->cast;
        $this->shape = $rule?->shape;
        $this->required = $rule->required ?? false;
        $this->forbidExplicitNull = $rule->forbidExplicitNull ?? false;
        $this->constructorValue = $rule->constructorValue ?? false;
        $this->constructorValueAllowMissing = $rule->constructorValueAllowMissing ?? false;
        $this->transform = match (true) {
            $rule?->cast !== null => HydrationTransform::Cast,
            $rule?->shape !== null => HydrationTransform::Shape,
            $rule?->noTransform === true => HydrationTransform::Identity,
            $this->nested !== null => HydrationTransform::Nested,
            default => HydrationTransform::Builtin,
        };
        $shape = $rule?->cast === null ? $rule?->shape : null;
        while ($shape?->kind === 'nullable') {
            $shape = $shape->item;
        }
        $this->inputShape = $rule->inputShape ?? match ($shape?->kind) {
            'dto' => InputShape::Object,
            'list' => InputShape::List,
            default => null,
        };
        $this->normalizeKeys = $shape->normalizeKeys ?? false;
        $this->collectionShape = $shape?->kind === 'list';
        $this->hydrationPolicy = $policy !== null && !$legacyProfile ? new DtoHydrationPolicy(
            namingStrategy: $policy->naming ?? NamingStrategy::None,
            dateTime: $policy->dateTime,
            emptyStringBehavior: $policy->emptyString ?? EmptyStringBehavior::Keep,
        ) : null;
    }

    public function sourcePath(NamingStrategy $naming): string
    {
        return $this->source ?? ($naming === NamingStrategy::SnakeCase ? $this->snakeName : $this->name);
    }

    /** @param array<string, object|null> $attributes */
    public function withAttributes(array $attributes): self
    {
        return new self(
            $this->property,
            $this->parameter,
            $this->rule,
            $this->policy,
            $this->legacyProfile,
            $this->origin,
            $attributes,
        );
    }
}
