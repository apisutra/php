<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\DataTransfer\Cast as CastAttribute;
use ApiSutra\Attributes\DataTransfer\DateTimeTo;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Casts\DateTimeCast;
use ApiSutra\Config\DateTimeSerializationPolicy;
use ApiSutra\Config\DtoSerializationPolicy;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Contracts\Interfaces\Casting\SerializationCastInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Serialization\Context\SerializationContext;
use ApiSutra\Serialization\Integration\HttpMappingAdapter;
use ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\Serialization\Rules\ReceiverOutput;
use ApiSutra\Serialization\Plan\SerializationValuePlan;
use ApiSutra\VO\Pipeline\PipelineContext;
use DateTimeInterface;
use JsonSerializable;
use ReflectionProperty;
use UnitEnum;

final readonly class SerializationValueResolver
{
    private PropertyTypeInspector $propertyTypeInspector;
    private EnumSerializationHelper $enumSerializer;

    public function __construct(
        private CastRegistry $casts,
        ?PropertyTypeInspector $propertyTypeInspector = null,
        private ?ReceiverOutput $receiverOutput = null,
    ) {
        $this->propertyTypeInspector = $propertyTypeInspector ?? new PropertyTypeInspector();
        $this->enumSerializer = new EnumSerializationHelper();
    }

    /**
     * @param callable(object, ?PipelineContext): array<string, mixed> $dtoSerializer
     */
    public function resolve(
        mixed $value,
        ?CastAttribute $cast,
        ?DateTimeTo $dateTimeTo,
        ReflectionProperty $property,
        ?PipelineContext $context,
        DtoSerializationPolicy $policy,
        callable $dtoSerializer,
        ?SerializationValuePlan $plan = null,
    ): mixed {
        if ($value === null) {
            return null;
        }
        $castClass = $cast->class ?? $plan?->castClass;
        $castArgs = $cast->args ?? $plan->castArgs ?? [];

        if ($this->receiverOutput !== null) {
            if (is_object($value)) {
                $this->receiverOutput->receiverFor($value::class);
            }
            $type = ($plan !== null
                ? $plan->typeFor($value, $this->propertyTypeInspector)
                : $this->propertyTypeInspector->resolvePropertyTypeByValue($property, $value));
            $customCast = $castClass !== null || $type !== null && $this->casts->get($type) !== null;
            if ($customCast && $this->receiverOutput->contains($value)) {
                throw new SerializationException(
                    new Message('serialization.cast_does_not_support_a_value_containing_a_receiver', ['value0' => $property->getDeclaringClass()->getName(), 'value1' => $property->getName()]),
                );
            }
        }

        if ($castClass !== null) {
            if (class_exists($castClass) && !is_subclass_of($castClass, SerializationCastInterface::class)) {
                throw $this->directionError($castClass, $property);
            }
            return $this->invoke(new $castClass(...$castArgs), $value, $context, $dtoSerializer, $property);
        }

        if ($value instanceof DtoInterface) {
            return $dtoSerializer($value, $context);
        }

        if (is_array($value)) {
            $result = $this->enumSerializer->serializeArray(
                items: $value,
                output: $policy->enumOutput,
                strictMode: $policy->strictEnums,
                dtoSerializer: fn (DtoInterface $dto): array => $dtoSerializer($dto, $context),
            );
            return $this->receiverOutput?->project($result, fn (object $dto): array => $dtoSerializer($dto, $context)) ?? $result;
        }

        $resolved = $this->resolveCastByType(
            ($plan !== null
                ? $plan->typeFor($value, $this->propertyTypeInspector)
                : $this->propertyTypeInspector->resolvePropertyTypeByValue($property, $value)),
            $dateTimeTo?->toPolicy($policy->dateTime) ?? $policy->dateTime,
        );
        if ($resolved !== null) {
            return $this->invoke($resolved, $value, $context, $dtoSerializer, $property);
        }

        if ($value instanceof UnitEnum) {
            return $this->enumSerializer->serializeEnum($value, $policy->enumOutput, $policy->strictEnums);
        }

        $resolved = $this->resolveCastByValue($value, $dateTimeTo?->toPolicy($policy->dateTime) ?? $policy->dateTime);
        if ($resolved !== null) {
            return $this->invoke($resolved, $value, $context, $dtoSerializer, $property);
        }

        $result = $this->serializeObject($value);
        return $result === $value && $this->receiverOutput !== null
            ? $this->receiverOutput->project($value, fn (object $dto): array => $dtoSerializer($dto, $context))
            : $result;
    }

    /** @param callable(object, ?PipelineContext): array<string, mixed> $dtoSerializer */
    private function invoke(
        object $cast,
        mixed $value,
        ?PipelineContext $pipeline,
        callable $dtoSerializer,
        ReflectionProperty $property,
    ): mixed {
        if (!$cast instanceof SerializationCastInterface) {
            throw $this->directionError($cast::class, $property);
        }
        return SerializationContext::invoke(
            fn (object $dto): array => $dtoSerializer($dto, $pipeline),
            HttpMappingAdapter::extensions($pipeline),
            fn (SerializationContext $context): mixed => $cast->serialize($value, $context),
        );
    }

    private function directionError(string $class, ReflectionProperty $property): ConfigurationException
    {
        return new ConfigurationException(new Message('serialization.serialization_cast_for_must_implement_serializationcastinterface', ['class' => $class, 'value1' => $property->getDeclaringClass()->getName(), 'value2' => $property->getName()]));
    }

    private function resolveCastByType(?string $type, DateTimeSerializationPolicy $dateTimePolicy): HydrationCastInterface|SerializationCastInterface|null
    {
        if ($type === null) {
            return null;
        }

        return match (true) {
            ($cast = $this->casts->get($type)) !== null => $cast,
            is_subclass_of($type, DateTimeInterface::class) => DateTimeCast::fromSerializationPolicy($dateTimePolicy),
            default => null,
        };
    }

    private function resolveCastByValue(mixed $value, DateTimeSerializationPolicy $dateTimePolicy): HydrationCastInterface|SerializationCastInterface|null
    {
        return match (true) {
            $value instanceof DateTimeInterface => DateTimeCast::fromSerializationPolicy($dateTimePolicy),
            default => null,
        };
    }

    private function serializeObject(mixed $value): mixed
    {
        if (!is_object($value)) {
            return $value;
        }

        return match (true) {
            method_exists($value, 'toArray') => $value->toArray(),
            $value instanceof JsonSerializable => $value->jsonSerialize(),
            default => $value,
        };
    }
}
