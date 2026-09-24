<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Flow;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\Request\RequestDiscriminator;
use ApiSutra\Attributes\Request\RequestOneOf;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Enums\Request\OneOfMode;
use BackedEnum;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionProperty;
use UnitEnum;

final class RequestContractValidator
{
    private readonly RequestContractFieldResolver $fieldResolver;

    public function __construct(?RequestContractFieldResolver $fieldResolver = null)
    {
        $this->fieldResolver = $fieldResolver ?? new RequestContractFieldResolver();
    }

    public function validate(RequestInterface $request): RequestContractValidationResult
    {
        $reflection = new ReflectionClass($request);
        $oneOfAttributes = $reflection->getAttributes(RequestOneOf::class);

        if ($oneOfAttributes === []) {
            return new RequestContractValidationResult(violation: null, oneOfDebug: null);
        }

        $oneOfContracts = array_map(
            static fn (ReflectionAttribute $attribute): RequestOneOf => $attribute->newInstance(),
            $oneOfAttributes,
        );
        $discriminator = ($reflection->getAttributes(RequestDiscriminator::class)[0] ?? null)?->newInstance();
        $rootValues = $this->extractRootValues($reflection, $request);

        $duplicates = $this->findDuplicateContractNames($oneOfContracts);
        if ($duplicates !== []) {
            $violation = new RequestContractViolation(
                contract: $duplicates[0],
                discriminatorField: $discriminator?->field,
                discriminatorValue: null,
                matchedVariant: null,
                filledVariants: [],
                violations: [[
                    'code' => 'duplicate_contract_name',
                    'message' => new Message('pipeline.duplicate_oneof_contract_name'),
                    'duplicates' => $duplicates,
                ]],
            );

            return new RequestContractValidationResult(violation: $violation);
        }

        $contractsDebug = [];
        foreach ($oneOfContracts as $contract) {
            $evaluation = $this->evaluateContract(
                contract: $contract,
                discriminator: $discriminator,
                rootValues: $rootValues,
            );

            $contractsDebug[] = $evaluation['debug'];
            if ($evaluation['violation'] instanceof RequestContractViolation) {
                return new RequestContractValidationResult(
                    violation: $evaluation['violation'],
                    oneOfDebug: $this->buildOneOfDebugPayload($contractsDebug),
                );
            }
        }

        return new RequestContractValidationResult(
            violation: null,
            oneOfDebug: $this->buildOneOfDebugPayload($contractsDebug),
        );
    }

    /**
     * @param array<int, RequestOneOf> $contracts
     * @return array<int, string>
     */
    private function findDuplicateContractNames(array $contracts): array
    {
        $counts = [];
        foreach ($contracts as $contract) {
            $name = trim($contract->name);
            $counts[$name] = ($counts[$name] ?? 0) + 1;
        }

        $duplicates = [];
        foreach ($counts as $name => $count) {
            if ($count > 1) {
                $duplicates[] = $name;
            }
        }

        return $duplicates;
    }

    /**
     * @param array<string, mixed> $rootValues
     * @return array{violation: ?RequestContractViolation, debug: array<string, mixed>}
     */
    private function evaluateContract(
        RequestOneOf $contract,
        ?RequestDiscriminator $discriminator,
        array $rootValues,
    ): array {
        $contractName = trim($contract->name);
        $violations = [];

        if ($contractName === '') {
            $violations[] = [
                'code' => 'empty_contract_name',
                'message' => new Message('pipeline.oneof_contract_name_must_not_be_empty'),
            ];
        }

        if ($contract->variants === []) {
            $violations[] = [
                'code' => 'empty_variants',
                'message' => new Message('pipeline.oneof_contract_has_no_alternatives'),
            ];
        }

        $variants = $this->normalizeVariants($contract->variants, $violations);
        $missingCommon = $this->resolveMissingFields(
            fields: $contract->requiredCommon,
            rootValues: $rootValues,
            unknownCode: 'required_common_unknown_field',
            violations: $violations,
        );
        if ($missingCommon !== []) {
            $violations[] = [
                'code' => 'required_common_missing',
                'message' => new Message('pipeline.required_common_fields_are_missing'),
                'fields' => $missingCommon,
            ];
        }

        $filledVariants = [];
        $variantMissingFields = [];
        foreach ($variants as $variant => $variantFields) {
            $variantState = $this->resolveVariantState(
                fields: $variantFields,
                rootValues: $rootValues,
                unknownCode: 'unknown_variant_field',
                violations: $violations,
            );

            if ($variantState['filled']) {
                $filledVariants[] = $variant;
            }

            $variantMissingFields[$variant] = $variantState['missing'];
            if ($variantState['filled'] && $variantState['missing'] !== []) {
                $violations[] = [
                    'code' => 'variant_fields_missing',
                    'message' => new Message('pipeline.required_fields_of_the_selected_alternative_are_missing'),
                    'variant' => $variant,
                    'fields' => $variantState['missing'],
                ];
            }
        }

        if ($filledVariants === []) {
            $violations[] = [
                'code' => 'none_selected',
                'message' => new Message('pipeline.no_oneof_alternative_is_filled'),
            ];
        }

        if ($contract->mode === OneOfMode::ExactlyOne && count($filledVariants) > 1) {
            $violations[] = [
                'code' => 'multiple_selected',
                'message' => new Message('pipeline.more_than_one_oneof_alternative_is_filled'),
                'variants' => $filledVariants,
            ];
        }

        $selectedVariant = count($filledVariants) === 1 ? $filledVariants[0] : null;
        $discriminatorField = null;
        $discriminatorValue = null;
        $discriminatorVariant = null;

        if ($discriminator !== null) {
            $discriminatorField = $discriminator->field;
            $resolvedDiscriminator = $this->resolveSingleField(
                field: $discriminatorField,
                rootValues: $rootValues,
                unknownCode: 'unknown_discriminator_field',
                violations: $violations,
            );

            if ($resolvedDiscriminator?->isFilled()) {
                $discriminatorValue = $this->normalizeDiscriminatorValue($resolvedDiscriminator->value);
                $discriminatorKey = $this->stringifyDiscriminator($discriminatorValue);
                $discriminatorVariant = $discriminator->map[$discriminatorKey] ?? null;

                if ($discriminatorVariant === null) {
                    $violations[] = [
                        'code' => 'unknown_discriminator_value',
                        'message' => new Message('pipeline.discriminator_contains_an_unknown_value'),
                        'value' => $discriminatorKey,
                    ];
                } elseif (!array_key_exists($discriminatorVariant, $variants)) {
                    $violations[] = [
                        'code' => 'discriminator_unknown_variant',
                        'message' => new Message('pipeline.discriminator_points_to_an_unknown_oneof_alternative'),
                        'variant' => $discriminatorVariant,
                    ];
                } else {
                    if ($selectedVariant !== null && $selectedVariant !== $discriminatorVariant) {
                        $violations[] = [
                            'code' => 'discriminator_mismatch',
                            'message' => new Message('pipeline.discriminator_does_not_match_the_filled_alternative'),
                            'expectedVariant' => $discriminatorVariant,
                            'actualVariant' => $selectedVariant,
                        ];
                    }

                    $missingExpectedFields = $variantMissingFields[$discriminatorVariant] ?? [];
                    if ($missingExpectedFields !== []) {
                        $violations[] = [
                            'code' => 'discriminator_variant_missing_fields',
                            'message' => new Message('pipeline.required_fields_of_the_discriminator_alternative_are_missing'),
                            'variant' => $discriminatorVariant,
                            'fields' => $missingExpectedFields,
                        ];
                    }

                    $prohibitedVariants = array_values(array_diff($filledVariants, [$discriminatorVariant]));
                    if ($prohibitedVariants !== []) {
                        $violations[] = [
                            'code' => 'prohibited_variant_fields',
                            'message' => new Message('pipeline.fields_belonging_to_other_discriminator_alternatives_are_filled'),
                            'expectedVariant' => $discriminatorVariant,
                            'variants' => $prohibitedVariants,
                        ];
                    }
                }
            }
        }

        $matchedVariant = $discriminatorVariant ?? $selectedVariant;
        $debug = [
            'contract' => $contractName,
            'matchedVariant' => $matchedVariant,
            'discriminator' => $discriminatorField === null ? null : [
                'field' => $discriminatorField,
                'value' => $discriminatorValue,
                'variant' => $discriminatorVariant,
            ],
        ];

        if ($violations === []) {
            return ['violation' => null, 'debug' => $debug];
        }

        $violation = new RequestContractViolation(
            contract: $contractName,
            discriminatorField: $discriminatorField,
            discriminatorValue: $discriminatorValue,
            matchedVariant: $matchedVariant,
            filledVariants: $filledVariants,
            violations: $violations,
        );

        return ['violation' => $violation, 'debug' => $debug];
    }

    /**
     * @param array<string, mixed> $rawVariants
     * @param array<int, array<string, mixed>> $violations
     * @return array<string, array<int, string>>
     */
    private function normalizeVariants(array $rawVariants, array &$violations): array
    {
        $variants = [];
        foreach ($rawVariants as $variant => $fields) {
            if (!is_string($variant) || trim($variant) === '') {
                $violations[] = [
                    'code' => 'invalid_variant_name',
                    'message' => new Message('pipeline.oneof_alternative_name_must_be_a_non_empty_string'),
                ];
                continue;
            }

            if (!is_array($fields) || $fields === []) {
                $violations[] = [
                    'code' => 'invalid_variant_fields',
                    'message' => new Message('pipeline.oneof_alternative_must_contain_a_list_of_fields'),
                    'variant' => $variant,
                ];
                continue;
            }

            $normalized = [];
            foreach ($fields as $field) {
                if (!is_string($field) || trim($field) === '') {
                    $violations[] = [
                        'code' => 'invalid_variant_field_name',
                        'message' => new Message('pipeline.oneof_alternative_field_must_be_a_non_empty_string'),
                        'variant' => $variant,
                    ];
                    continue;
                }

                $normalized[] = trim($field);
            }

            $variants[$variant] = array_values(array_unique($normalized));
        }

        return $variants;
    }

    /**
     * @param array<int, string> $fields
     * @param array<string, mixed> $rootValues
     * @param array<int, array<string, mixed>> $violations
     * @return array<int, string>
     */
    private function resolveMissingFields(
        array $fields,
        array $rootValues,
        string $unknownCode,
        array &$violations,
    ): array {
        $resolved = $this->resolveFields(
            fields: $fields,
            rootValues: $rootValues,
            unknownCode: $unknownCode,
            violations: $violations,
        );

        $missing = [];
        foreach ($resolved as $field => $resolvedField) {
            if (!$resolvedField->isFilled()) {
                $missing[] = $field;
            }
        }

        return $missing;
    }

    /**
     * @param array<int, string> $fields
     * @param array<string, mixed> $rootValues
     * @param array<int, array<string, mixed>> $violations
     * @return array{filled: bool, missing: array<int, string>}
     */
    private function resolveVariantState(
        array $fields,
        array $rootValues,
        string $unknownCode,
        array &$violations,
    ): array {
        $resolved = $this->resolveFields(
            fields: $fields,
            rootValues: $rootValues,
            unknownCode: $unknownCode,
            violations: $violations,
        );

        $filled = false;
        $missing = [];
        foreach ($resolved as $field => $resolvedField) {
            if ($resolvedField->isFilled()) {
                $filled = true;
                continue;
            }

            $missing[] = $field;
        }

        return [
            'filled' => $filled,
            'missing' => $missing,
        ];
    }

    /**
     * @param array<int, string> $fields
     * @param array<string, mixed> $rootValues
     * @param array<int, array<string, mixed>> $violations
     * @return array<string, RequestContractFieldValue>
     */
    private function resolveFields(
        array $fields,
        array $rootValues,
        string $unknownCode,
        array &$violations,
    ): array {
        $resolved = [];
        foreach ($fields as $field) {
            if (!is_string($field) || trim($field) === '') {
                continue;
            }

            $fieldValue = $this->resolveSingleField(
                field: $field,
                rootValues: $rootValues,
                unknownCode: $unknownCode,
                violations: $violations,
            );
            if (!$fieldValue instanceof RequestContractFieldValue) {
                continue;
            }

            $resolved[$fieldValue->path] = $fieldValue;
        }

        return $resolved;
    }

    /**
     * @param array<string, mixed> $rootValues
     * @param array<int, array<string, mixed>> $violations
     */
    private function resolveSingleField(
        string $field,
        array $rootValues,
        string $unknownCode,
        array &$violations,
    ): ?RequestContractFieldValue {
        $resolved = $this->fieldResolver->resolve($field, $rootValues, $unknownCode);
        if ($resolved->failed()) {
            $violation = $resolved->violation;
            if (is_array($violation)) {
                $violations[] = $violation;
            }

            return null;
        }

        return $resolved->field;
    }

    /**
     * @param array<int, array<string, mixed>> $contractsDebug
     * @return array<string, mixed>|null
     */
    private function buildOneOfDebugPayload(array $contractsDebug): ?array
    {
        if ($contractsDebug === []) {
            return null;
        }

        $first = $contractsDebug[0];

        return [
            'contract' => $first['contract'] ?? null,
            'matchedVariant' => $first['matchedVariant'] ?? null,
            'discriminator' => $first['discriminator'] ?? null,
            'contracts' => $contractsDebug,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractRootValues(ReflectionClass $reflection, object $request): array
    {
        $values = [];
        foreach ($reflection->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $values[$property->getName()] = $this->readProperty($request, $property);
        }

        return $values;
    }

    private function readProperty(object $request, ReflectionProperty $property): mixed
    {
        if (!$property->isInitialized($request)) {
            return null;
        }

        if ($property->isPublic()) {
            return $request->{$property->getName()};
        }


        return $property->getValue($request);
    }

    private function stringifyDiscriminator(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value) || is_bool($value)) {
            return (string) $value;
        }

        return serialize($value);
    }

    private function normalizeDiscriminatorValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof UnitEnum) {
            return $value->name;
        }

        return $value;
    }
}
