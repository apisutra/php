<?php

declare(strict_types=1);

namespace ApiSutra\Pipeline\Flow;

use ApiSutra\Localization\Message;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Support\ArrayPath;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

final class RequestContractFieldResolver
{
    /**
     * @param array<string, mixed> $rootValues
     */
    public function resolve(string $path, array $rootValues, string $unknownCode): RequestContractFieldResolution
    {
        $normalizedPath = trim($path);
        if ($normalizedPath === '') {
            return new RequestContractFieldResolution(
                field: null,
                violation: [
                    'code' => 'invalid_field_path',
                    'message' => new Message('pipeline.oneof_field_path_must_not_be_empty'),
                    'field' => $path,
                ],
            );
        }

        $segments = explode('.', $normalizedPath);
        $root = $segments[0] ?? '';
        if (!array_key_exists($root, $rootValues)) {
            return new RequestContractFieldResolution(
                field: null,
                violation: [
                    'code' => $unknownCode,
                    'message' => new Message('pipeline.contract_refers_to_an_unknown_field'),
                    'field' => $normalizedPath,
                ],
            );
        }

        $rootValue = $rootValues[$root];
        if (count($segments) === 1) {
            return new RequestContractFieldResolution(
                field: new RequestContractFieldValue(
                    path: $normalizedPath,
                    state: $this->stateFromScalar($rootValue),
                    value: $rootValue,
                ),
            );
        }

        if ($rootValue === null) {
            return new RequestContractFieldResolution(
                field: new RequestContractFieldValue(
                    path: $normalizedPath,
                    state: ValueState::Null,
                    value: null,
                ),
            );
        }

        $payload = $this->normalizePathPayload($rootValue);
        if (!is_array($payload)) {
            return new RequestContractFieldResolution(
                field: null,
                violation: [
                    'code' => 'invalid_dot_path_root',
                    'message' => new Message('pipeline.dot_path_requires_an_array_object_at_the_root'),
                    'field' => $normalizedPath,
                    'root' => $root,
                    'rootType' => get_debug_type($rootValue),
                ],
            );
        }

        $nestedPath = implode('.', array_slice($segments, 1));
        $result = ArrayPath::getByPathWithStatus($payload, $nestedPath);

        return new RequestContractFieldResolution(
            field: new RequestContractFieldValue(
                path: $normalizedPath,
                state: $result->state,
                value: $result->value,
            ),
        );
    }

    private function stateFromScalar(mixed $value): ValueState
    {
        return $value === null
            ? ValueState::Null
            : ValueState::Present;
    }

    private function normalizePathPayload(mixed $value): ?array
    {
        return match (true) {
            is_array($value) => $this->normalizeArray($value),
            is_object($value) => $this->normalizeObject($value),
            default => null,
        };
    }

    /**
     * @param array<string|int, mixed> $payload
     * @return array<string|int, mixed>
     */
    private function normalizeArray(array $payload): array
    {
        foreach ($payload as $key => $item) {
            if (is_array($item)) {
                $payload[$key] = $this->normalizeArray($item);
                continue;
            }

            if (!is_object($item)) {
                continue;
            }

            $normalized = $this->normalizeObject($item);
            $payload[$key] = $normalized ?? $item;
        }

        return $payload;
    }

    /**
     * @return array<string|int, mixed>|null
     */
    private function normalizeObject(object $value): ?array
    {
        $asArray = $this->resolveObjectAsArray($value);
        if ($asArray === null) {
            return null;
        }

        return $this->normalizeArray($asArray);
    }

    /**
     * @return array<string|int, mixed>|null
     */
    private function resolveObjectAsArray(object $value): ?array
    {
        if (method_exists($value, 'toArray')) {
            try {
                $resolved = $value->toArray();
                if (is_array($resolved)) {
                    return $resolved;
                }
            } catch (Throwable) {
                // Игнорируем и продолжаем fallback-ветки.
            }
        }

        if ($value instanceof JsonSerializable) {
            $serialized = $value->jsonSerialize();
            if (is_array($serialized)) {
                return $serialized;
            }
        }

        $reflection = new ReflectionClass($value);
        $result = [];
        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic() || !$property->isInitialized($value)) {
                continue;
            }

            $result[$property->getName()] = $property->getValue($value);
        }

        return $result;
    }
}
