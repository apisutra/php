<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Hydration;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\HydrationValueValidator;
use ApiSutra\Serialization\Rules\ConstructorValues;
use ApiSutra\Serialization\Rules\HydratedProperty;
use Error;
use ReflectionClass;
use ReflectionException;
use ReflectionParameter;
use ReflectionProperty;
use TypeError;

/** @internal Одна фаза создания: constructor defaults, constructorValue и оставшиеся свойства. */
final readonly class HydrationObjectFactory
{
    public function __construct(private HydrationValueValidator $valueValidator = new HydrationValueValidator())
    {
    }

    /**
     * @param array<string, mixed> $values
     * @param array<string, array{property: ReflectionProperty, input: HydratedProperty}> $constructorChecks
     */
    public function create(HydrationPlan $plan, array $values, array $constructorChecks): object
    {
        $constructor = $plan->constructor;
        $reflection = $plan->reflection;
        if ($constructor === null) {
            return $this->instantiateWithoutConstructorMetadata($reflection, $plan->fields, $values);
        }

        $constructorArgs = $this->buildConstructorArguments($values, $constructor);
        $remainingAssignments = $this->buildRemainingPropertyAssignments(
            values: $values,
            properties: $plan->fields,
            constructorChecks: $constructorChecks,
        );

        if ($remainingAssignments === [] && $constructorChecks === []) {
            return $reflection->newInstanceArgs($constructorArgs);
        }

        return $this->instantiateWithPropertyFill(
            reflection: $reflection,
            constructorArgs: $constructorArgs,
            assignments: $remainingAssignments,
            constructorChecks: $constructorChecks,
        );
    }

    /**
     * @param array<string, mixed> $values
     * @param list<array{name: string, reflection: ReflectionParameter, hasDefault: bool}> $constructor
     * @return array<string, mixed>
     */
    private function buildConstructorArguments(array $values, array $constructor): array
    {
        $args = [];

        foreach ($constructor as $parameter) {
            $paramName = $parameter['name'];

            if (array_key_exists($paramName, $values)) {
                $reflection = $parameter['reflection'];
                $this->valueValidator->assertValue(
                    $values[$paramName],
                    $reflection->getType(),
                    $reflection->getDeclaringClass(),
                    $paramName,
                );
                $args[$paramName] = $values[$paramName];
                continue;
            }

            if (!$parameter['hasDefault'] && !$parameter['reflection']->isVariadic()) {
                throw HydrationException::invalidValue(
                    'required_field_missing',
                    (string) ($parameter['reflection']->getType() ?? 'mixed'),
                    'missing',
                    $paramName,
                );
            }
        }

        return $args;
    }

    /**
     * @param array<string, mixed> $values
     * @param list<HydrationFieldPlan> $properties
     * @param array<string, array{property: ReflectionProperty, input: HydratedProperty}> $constructorChecks
     * @return array<string, array{property: ReflectionProperty, value: mixed}>
     */
    private function buildRemainingPropertyAssignments(
        array $values,
        array $properties,
        array $constructorChecks = [],
    ): array {
        $assignments = [];

        foreach ($properties as $propertyMeta) {
            $name = $propertyMeta->name;

            if ($propertyMeta->parameter !== null || isset($constructorChecks[$name])) {
                continue;
            }

            $property = $propertyMeta->property;

            if (array_key_exists($name, $values)) {
                $assignments[$name] = [
                    'property' => $property,
                    'value' => $values[$name],
                ];
                continue;
            }

            if ($property->getType()?->allowsNull() === true) {
                $assignments[$name] = [
                    'property' => $property,
                    'value' => null,
                ];
                continue;
            }

            if (!$property->hasDefaultValue()) {
                throw HydrationException::invalidValue(
                    'required_field_missing',
                    (string) ($property->getType() ?? 'mixed'),
                    'missing',
                    $name,
                );
            }
        }

        return $assignments;
    }

    /**
     * @param list<HydrationFieldPlan> $properties
     * @param array<string, mixed> $values
     */
    private function instantiateWithoutConstructorMetadata(
        ReflectionClass $reflection,
        array $properties,
        array $values,
    ): object {
        $assignments = $this->buildRemainingPropertyAssignments(
            values: $values,
            properties: $properties,
        );

        if ($assignments === []) {
            return $reflection->newInstance();
        }

        return $this->instantiateWithPropertyFill(
            reflection: $reflection,
            constructorArgs: [],
            assignments: $assignments,
        );
    }

    /**
     * @param array<string, mixed> $constructorArgs
     * @param array<string, array{property: ReflectionProperty, value: mixed}> $assignments
     * @param array<string, array{property: ReflectionProperty, input: HydratedProperty}> $constructorChecks
     */
    private function instantiateWithPropertyFill(
        ReflectionClass $reflection,
        array $constructorArgs,
        array $assignments,
        array $constructorChecks = [],
    ): object {
        $object = $reflection->newInstanceWithoutConstructor();

        $constructor = $reflection->getConstructor();
        if ($constructor !== null) {
            $constructor->invokeArgs($object, $constructorArgs);
        }

        $comparator = new ConstructorValues();
        foreach ($constructorChecks as $check) {
            $comparator->check($object, $check['property'], $check['input']);
        }
        $this->assignPropertyValues($object, $assignments);

        return $object;
    }

    /**
     * @param array<string, array{property: ReflectionProperty, value: mixed}> $assignments
     */
    private function assignPropertyValues(object $dto, array $assignments): void
    {
        foreach ($assignments as $name => $assignment) {
            $property = $assignment['property'];

            if ($property->isInitialized($dto)) {
                throw new ConfigurationException(
                    new Message('serialization.property_already_initialized', ['class' => $dto::class, 'property' => $name]),
                );
            }

            try {
                $property->setValue($dto, $assignment['value']);
            } catch (ReflectionException | Error | TypeError $exception) {
                throw new ConfigurationException(
                    new Message('serialization.failed_to_initialize_dto_property_through_hydration_fallback', ['value0' => $dto::class, 'name' => $name, 'value2' => $exception->getMessage()]),
                    previous: $exception,
                );
            }
        }
    }
}
