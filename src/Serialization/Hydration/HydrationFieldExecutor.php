<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Hydration;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\DataTransfer\DefaultValue;
use ApiSutra\Collections\AbstractTypedCollection;
use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Enums\DataTransfer\EmptyStringBehavior;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\BuiltinHydrationCaster;
use ApiSutra\Serialization\Concerns\ReflectionHelperTrait;
use ApiSutra\Serialization\HydrationValueValidator;
use ApiSutra\Serialization\NativePropertyValue;
use ApiSutra\Serialization\NestedObjectTypeResolver;
use ApiSutra\Serialization\Rules\ConstructorValues;
use ApiSutra\Serialization\Rules\HydratedProperty;
use ApiSutra\Serialization\Rules\HydrationScope;
use ApiSutra\Serialization\Rules\NestedValueProcessor;
use ApiSutra\Serialization\Rules\RuleValueProcessor;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\Serialization\Rules\ScalarValues;
use ApiSutra\Serialization\Rules\SourceConsumption;
use ApiSutra\Serialization\Rules\SourceLocation;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Serialization\VO\ResolvedDtoHydration;
use ApiSutra\Support\ArrayPath;
use ApiSutra\Support\PathResult;

/** @internal Закрытые стадии поля; значения и provenance принадлежат одному вызову. */
final readonly class HydrationFieldExecutor
{
    use ReflectionHelperTrait;

    private ScalarValues $scalars;
    private RuleValueProcessor $rules;
    private NestedValueProcessor $nestedValues;
    private NestedObjectTypeResolver $nestedTypes;
    private LegacyNestedHydrator $legacyNested;
    private HydrationValueValidator $validator;

    public function __construct(private BuiltinHydrationCaster $builtin)
    {
        $this->scalars = new ScalarValues();
        $this->rules = new RuleValueProcessor($this->scalars);
        $this->nestedValues = new NestedValueProcessor($this->rules);
        $this->nestedTypes = new NestedObjectTypeResolver();
        $this->legacyNested = new LegacyNestedHydrator($this->nestedTypes);
        $this->validator = new HydrationValueValidator();
    }

    /** @param array<string, mixed> $source */
    public function hydrate(
        array $source,
        HydrationFieldPlan $field,
        ResolvedDtoHydration $hydration,
        HydrationScope $scope,
    ): HydratedProperty {
        if ($field->hydrationPolicy !== null) {
            $hydration = new ResolvedDtoHydration($field->hydrationPolicy, $hydration->casts);
        }
        $primary = $field->sourcePath($hydration->policy->namingStrategy);
        [$resolved, $path] = $this->select($source, $primary, $field->fallbacks);
        $segments = explode('.', $path);
        $location = $scope->location()->descend(
            $segments,
            kind: $resolved->isMissing() ? SourcePathKind::Expected : SourcePathKind::Resolved,
        );
        if ($location->kind === SourcePathKind::Expected) {
            $candidates = array_map(
                fn (string $candidate): SourceLocation => $scope->location()->descend(explode('.', $candidate)),
                [$primary, ...$field->fallbacks],
            );
            $location = new SourceLocation(
                $location->segments,
                $location->safeSegments,
                $location->kind,
                array_map(
                    static fn (SourceLocation $item): string => SourceLocation::pointer($item->segments),
                    $candidates,
                ),
                array_map(
                    static fn (SourceLocation $item): string => SourceLocation::pointer($item->safeSegments),
                    $candidates,
                ),
            );
        }
        try {
            return $scope->at($location, function () use (
                $source,
                $field,
                $hydration,
                $scope,
                $resolved,
                $segments,
                $location,
            ): HydratedProperty {
                $state = $resolved->state;
                $value = $resolved->value;
                $consumed = $resolved->isMissing() ? new SourceConsumption() : SourceConsumption::all();
                $this->assertInput($state, $value, $field);
                if (
                    $field->handler === null && $field->cast === null
                    && $state === ValueState::Present && is_string($value)
                ) {
                    $normalize = $field->emptyStringAsNull?->matches($value)
                        ?? match ($hydration->policy->emptyStringBehavior) {
                        EmptyStringBehavior::Keep => false,
                        EmptyStringBehavior::NullIfEmpty => $value === '',
                        EmptyStringBehavior::NullIfBlank => trim($value) === '',
                        };
                    if ($normalize) {
                        $state = ValueState::Null;
                        $value = null;
                    }
                }
                $defaultApplied = false;
                $default = $field->externalDefault;
                if ($default !== null && in_array($state, $default->when, true)) {
                    $spec = $default->provider;
                    $value = $spec === null ? $default->value
                        : $scope->provide(new $spec->class(...$spec->args), $value, $state, $source);
                    $defaultApplied = true;
                } elseif ($field->default !== null && in_array($state, $field->default->when, true)) {
                    $value = $this->defaultValue($field->default, $value, $state, $source, $scope);
                    $defaultApplied = true;
                }
                if ($defaultApplied) {
                    $state = $value === null ? ValueState::Null : ValueState::Present;
                    $location = $location->boundary();
                }
                if ($state === ValueState::Missing) {
                    if ($this->autoCollection($field)) {
                        $value = HydrationCollections::wrap([], $this->getPrimaryType($field->property));
                        return new HydratedProperty($value, ValueState::Present, $segments, $location, $consumed);
                    }
                    if ($field->constructorValue && !$field->constructorValueAllowMissing) {
                        throw HydrationException::invalidValue(
                            'required_field_missing',
                            (string) $field->property->getType(),
                            'missing',
                        );
                    }
                    return new HydratedProperty(null, $state, $segments, $location, $consumed);
                }
                return $scope->at($location, fn (): HydratedProperty => $this->transform(
                    $value,
                    $state,
                    $segments,
                    $location,
                    $consumed,
                    $field,
                    $hydration,
                    $scope,
                    $defaultApplied,
                    !$resolved->isMissing(),
                ));
            });
        } catch (HydrationException $exception) {
            throw $exception->prependPath($field->name);
        }
    }

    private function assertInput(ValueState $state, mixed $value, HydrationFieldPlan $field): void
    {
        if ($field->required && $state === ValueState::Missing) {
            throw HydrationException::invalidValue(
                'required_field_missing',
                (string) ($field->property->getType() ?? 'mixed'),
                'missing',
            );
        }
        if ($field->forbidExplicitNull && $state === ValueState::Null) {
            throw HydrationException::invalidValue('explicit_null_not_allowed', 'non-null input', 'null');
        }
        if ($state === ValueState::Present && $field->inputShape !== null) {
            $this->rules->assertInput($value, $field->inputShape, $field->normalizeKeys);
        }
    }

    /** @param list<string> $segments */
    private function transform(
        mixed $value,
        ValueState $state,
        array $segments,
        SourceLocation $location,
        SourceConsumption $consumed,
        HydrationFieldPlan $field,
        ResolvedDtoHydration $hydration,
        HydrationScope $scope,
        bool $defaultApplied,
        bool $sourcePresent,
    ): HydratedProperty {
        $policy = $field->policy;
        switch ($field->transform) {
            case HydrationTransform::Cast:
                $spec = $field->handler;
                $value = $scope->cast(new $spec->class(...$spec->args), $value);
                $location = $location->boundary();
                if ($field->shape !== null) {
                    $value = $scope->at($location, fn (): mixed => $this->rules->transform(
                        $value,
                        $field->shape,
                        $policy,
                        $scope,
                        resultOnly: true,
                    )->value);
                }
                break;
            case HydrationTransform::Shape:
                if ($value !== null) {
                    $shaped = $this->rules->transform(
                        $value,
                        $field->shape,
                        $policy,
                        $scope,
                        readyDto: $defaultApplied,
                    );
                    $value = $shaped->value;
                    if (!$defaultApplied && $sourcePresent) {
                        $consumed = $shaped->consumed;
                    }
                    if (is_array($value) && $field->collectionShape) {
                        $value = HydrationCollections::wrap($value, $this->getPrimaryType($field->property));
                    }
                }
                break;
            case HydrationTransform::Nested:
                $nested = $field->nested;
                if (
                    $policy !== null && is_array($value)
                    && $this->nestedTypes->resolve($nested, $field->property) === null
                ) {
                    $propertyType = $this->getPrimaryType($field->property);
                    $target = $nested->type ?? $propertyType;
                    $processed = $this->nestedValues->process($value, $nested, $target, $scope);
                    $value = $processed->value;
                    if ($this->legacyNested->isDiscriminated($nested) || ($target !== null && class_exists($target))) {
                        $value = HydrationCollections::wrap($value, $propertyType);
                    }
                    if (!$defaultApplied && $sourcePresent) {
                        $consumed = $processed->consumed;
                    }
                } else {
                    $value = $this->legacyNested->hydrate($value, $nested, $field->property, $scope);
                }
                break;
            case HydrationTransform::Builtin:
                $custom = $this->builtin->usesCustomCast($value, $field->cast, $field->property, $hydration, $policy);
                $value = $this->builtin->hydrate(
                    $value,
                    $field->cast,
                    $field->dateTimeFrom,
                    $field->property,
                    $hydration,
                    $scope->context(),
                    $scope,
                    $policy,
                );
                if ($custom) {
                    $location = $location->boundary();
                }
                break;
            case HydrationTransform::Identity:
                break;
        }
        $value = $scope->at($location, fn (): mixed => $this->validate($value, $field));
        return new HydratedProperty($value, $state, $segments, $location, $consumed);
    }

    private function validate(mixed $value, HydrationFieldPlan $field): mixed
    {
        $property = $field->property;
        $parameter = $field->parameter;
        $strict = $field->policy?->scalars === ScalarPolicy::Strict;
        if ($strict) {
            $value = $this->scalars->strictReflection(
                $value,
                $parameter === null ? $property->getType() : $parameter->getType(),
                $parameter?->getDeclaringClass() ?? $property->getDeclaringClass(),
            );
        }
        if ($parameter === null && !$strict) {
            $this->validator->assertValue($value, $property->getType(), $property->getDeclaringClass(), '');
        }
        if ($field->constructorValue) {
            if (!$strict) {
                $value = (new NativePropertyValue())->resolve($value, $property);
            }
            (new ConstructorValues())->assertInput($value);
        }
        return $value;
    }

    private function autoCollection(HydrationFieldPlan $field): bool
    {
        if ($field->default !== null && in_array(ValueState::Missing, $field->default->when, true)) {
            return false;
        }
        $type = $field->property->getType();
        if ($type === null || $type->allowsNull()) {
            return false;
        }
        $primary = $this->getPrimaryType($field->property);
        return is_string($primary) && class_exists($primary)
            && is_subclass_of($primary, AbstractTypedCollection::class);
    }

    /** @param array<string, mixed> $source */
    private function defaultValue(
        DefaultValue $default,
        mixed $value,
        ValueState $state,
        array $source,
        HydrationScope $scope,
    ): mixed {
        if ($default->value !== null && $default->provider !== null) {
            throw new ConfigurationException(new Message('serialization.defaultvalue_cannot_specify_both_value_and_provider'));
        }
        if ($default->value === null && $default->provider === null) {
            throw new ConfigurationException(new Message('serialization.defaultvalue_must_specify_value_or_provider'));
        }
        if ($default->provider === null) {
            return $default->value;
        }
        if (
            !class_exists($default->provider)
            || !is_subclass_of($default->provider, DefaultValueProviderInterface::class)
        ) {
            throw new ConfigurationException(
                new Message('serialization.defaultvalue_provider_must_implement_defaultvalueproviderinterface'),
            );
        }
        return $scope->provide(new $default->provider(), $value, $state, $source);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $fallbacks
     * @return array{PathResult, string}
     */
    private function select(array $data, string $primary, array $fallbacks): array
    {
        $resolved = ArrayPath::getByPathWithStatus($data, $primary);
        if (!$resolved->isMissing()) {
            return [$resolved, $primary];
        }
        foreach ($fallbacks as $fallback) {
            $resolved = ArrayPath::getByPathWithStatus($data, $fallback);
            if (!$resolved->isMissing()) {
                return [$resolved, $fallback];
            }
        }
        return [$resolved, $primary];
    }
}
