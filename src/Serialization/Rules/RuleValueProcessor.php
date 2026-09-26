<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ApiSutra\Localization\Message;
use ApiSutra\Collections\AbstractCollection;
use ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;
use ApiSutra\Enums\DataTransfer\NestedUnknownVariant;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Support\ArrayPath;
use ApiSutra\Serialization\Input\InputShapeGuard;
use Stringable;

/** @internal Преобразует описанную форму; состояние источника принадлежит текущему вызову. */
final readonly class RuleValueProcessor
{
    public function __construct(private ScalarValues $scalars = new ScalarValues())
    {
    }

    public function transform(
        mixed $value,
        ValueShape $shape,
        RulePolicy $policy,
        HydrationScope $scope,
        bool $resultOnly = false,
        bool $readyDto = false,
    ): ShapeResult {
        if ($shape->kind === 'nullable') {
            return $value === null
                ? new ShapeResult(null, SourceConsumption::all())
                : $this->transform($value, $shape->item, $policy, $scope, $resultOnly, $readyDto);
        }
        if ($shape->kind === 'mixed') {
            return new ShapeResult($value, SourceConsumption::all());
        }
        if ($value === null && $shape->kind !== 'variants') {
            throw HydrationException::invalidValue('null_not_allowed', $shape->kind, 'null');
        }
        if ($shape->kind === 'scalar') {
            return new ShapeResult(
                $this->scalars->coerce($value, $shape->types, $policy->scalars ?? ScalarPolicy::Legacy),
                SourceConsumption::all(),
            );
        }
        if ($shape->kind === 'dto') {
            $class = $shape->class ?? throw new ConfigurationException(new Message('serialization.valueshape_dto_requires_a_class'));
            if (($readyDto || $resultOnly) && $value instanceof $class) {
                return new ShapeResult($value, SourceConsumption::all());
            }
            if ($resultOnly) {
                throw HydrationException::invalidValue('invalid_field_type', $class, get_debug_type($value));
            }
            return new ShapeResult($scope->hydrateDto($value, $class, $shape->emptyListAsObject), SourceConsumption::all());
        }
        if ($shape->kind === 'list') {
            return $this->list($value, $shape, $policy, $scope, $resultOnly, $readyDto);
        }
        if ($readyDto && is_object($value)) {
            foreach ($shape->map as $class) {
                if ($value instanceof $class) {
                    return new ShapeResult($value, SourceConsumption::all());
                }
            }
        }
        return $this->variant($value, $shape, $scope, $resultOnly);
    }

    public function assertInput(mixed $value, InputShape $shape, bool $normalizeKeys = false, ?InputShape $source = null, bool $emptyListAsObject = false): void
    {
        InputShapeGuard::assert($value, $shape, $normalizeKeys, $source, $emptyListAsObject);
    }

    private function list(
        mixed $value,
        ValueShape $shape,
        RulePolicy $policy,
        HydrationScope $scope,
        bool $resultOnly,
        bool $readyDto,
    ): ShapeResult {
        $input = $resultOnly && $value instanceof AbstractCollection ? $value->all() : $value;
        $this->assertInput($input, InputShape::List, !$resultOnly && $shape->normalizeKeys, $resultOnly ? null : $scope->shape($input));
        return $scope->node($input, function () use ($input, $value, $shape, $policy, $scope, $resultOnly, $readyDto): ShapeResult {
            $result = [];
            $consumed = new SourceConsumption();
            $consumed->projection = true;
            $index = 0;
            $safe = $scope->hasListIndices($input);
            foreach ($input as $key => $raw) {
                try {
                    $processed = $scope->descend([$key], fn (): ShapeResult => $this->listItem(
                        $raw,
                        $shape,
                        $policy,
                        $scope,
                        $resultOnly,
                        $readyDto,
                    ), $safe);
                    $consumed->mergeAt([$key], $processed->consumed);
                    if (!$processed->skip) {
                        $result[] = $processed->value;
                    }
                } catch (HydrationException $exception) {
                    throw $exception->prependPath('[' . $index . ']');
                }
                $index++;
            }
            return new ShapeResult($resultOnly && $value instanceof AbstractCollection ? $value : $result, $consumed);
        });
    }

    private function listItem(
        mixed $raw,
        ValueShape $list,
        RulePolicy $policy,
        HydrationScope $scope,
        bool $resultOnly,
        bool $readyDto,
    ): ShapeResult {
        $value = $raw;
        $segments = [];
        $location = $scope->location();
        if (!$resultOnly && $list->each !== null) {
            $segments = explode('.', $list->each);
            $selected = ArrayPath::getByPathWithStatus(is_object($raw) ? get_object_vars($raw) : $raw, $list->each);
            $value = $selected->value;
            $location = $location->descend($segments, kind: $selected->isMissing() ? SourcePathKind::Expected : SourcePathKind::Resolved);
        }
        $processed = $scope->at($location, function () use ($value, $list, $policy, $scope, $resultOnly, $readyDto): ShapeResult {
            if (!$resultOnly && $list->itemCast !== null) {
                $spec = $list->itemCast;
                $cast = new $spec->class(...$spec->args);
                $value = $scope->cast($cast, $value);
                return $scope->boundary(fn (): ShapeResult => $this->transform(
                    $value,
                    $list->item,
                    $policy,
                    $scope,
                    readyDto: true,
                ));
            }
            return $this->transform($value, $list->item, $policy, $scope, $resultOnly, $readyDto);
        }, $segments);
        if ($processed->skip) {
            return new ShapeResult(null, SourceConsumption::all(), true);
        }
        $consumed = new SourceConsumption();
        $consumed->mergeAt($segments, $list->itemCast !== null && !$resultOnly ? SourceConsumption::all() : $processed->consumed);
        return new ShapeResult($processed->value, $consumed);
    }

    private function variant(mixed $value, ValueShape $shape, HydrationScope $scope, bool $resultOnly): ShapeResult
    {
        if ($resultOnly) {
            foreach ($shape->map as $class) {
                if ($value instanceof $class) {
                    return new ShapeResult($value, SourceConsumption::all());
                }
            }
            throw HydrationException::invalidValue('invalid_field_type', 'variant DTO', get_debug_type($value));
        }
        $source = is_object($value) ? get_object_vars($value) : $value;
        $payload = $value;
        $segments = [];
        if ($shape->mode === NestedDiscriminatorMode::Key) {
            if ($shape->discriminator !== '') {
                $segments = explode('.', $shape->discriminator);
                $source = ArrayPath::getByPath($source, $shape->discriminator);
            }
            $key = is_array($source) && $source !== [] ? array_key_first($source) : null;
            $tag = $key === null ? null : (string) $key;
            $payload = $key === null ? null : $source[$key];
            if ($key !== null) {
                $segments[] = $key;
            }
        } else {
            $tag = ArrayPath::getByPath($source, $shape->discriminator);
            $tag = is_scalar($tag) || $tag instanceof Stringable ? (string) $tag : null;
        }
        $class = $tag === null ? null : ($shape->map[$tag] ?? null);
        if ($class === null) {
            return match ($shape->unknown) {
                NestedUnknownVariant::Skip => new ShapeResult(null, SourceConsumption::all(), true),
                NestedUnknownVariant::KeepRaw => new ShapeResult($value, SourceConsumption::all()),
                NestedUnknownVariant::Error => throw HydrationException::invalidValue(
                    'unknown_nested_variant',
                    'variant: ' . implode('|', array_keys($shape->map)),
                    $tag === null ? 'missing' : 'string',
                ),
            };
        }
        $location = $scope->location()->descend($segments);
        $dto = $scope->at($location, function () use ($payload, $class, $scope): object {
            return $scope->hydrateDto($payload, $class);
        }, $segments);
        $consumed = new SourceConsumption();
        $consumed->mergeAt($segments, SourceConsumption::all());
        return new ShapeResult($dto, $consumed);
    }
}
