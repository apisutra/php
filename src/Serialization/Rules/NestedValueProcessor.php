<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Enums\DataTransfer\NestedDiscriminatorMode;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Support\ArrayPath;
use ApiSutra\VO\Files\Base64File;
use ReflectionClass;

/** @internal Сохраняет происхождение атрибутного списка и прежний срок жизни itemCast. */
final readonly class NestedValueProcessor
{
    public function __construct(private RuleValueProcessor $values)
    {
    }

    public function process(array $input, Nested $nested, ?string $target, HydrationScope $scope): ShapeResult
    {
        $variants = $nested->map !== null
            && ($nested->discriminator !== null || $nested->discriminatorMode === NestedDiscriminatorMode::Key);
        $shape = $variants ? ValueShape::variants(
            $nested->discriminator ?? '',
            $nested->map,
            $nested->discriminatorMode,
            $nested->unknownVariant,
        ) : null;
        foreach ($nested->map ?? [] as $class) {
            if (!is_string($class) || !class_exists($class) || (new ReflectionClass($class))->isAbstract() || enum_exists($class)) {
                throw new ConfigurationException(new Message('serialization.nested_map_must_contain_available_dto_classes'));
            }
        }
        $cast = null;
        if ($nested->itemCast !== null && trim($nested->itemCast) !== '') {
            $class = $nested->itemCast;
            if (!is_subclass_of($class, HydrationCastInterface::class)) {
                throw new ConfigurationException(new Message('serialization.nested_itemcast_must_implement_hydrationcastinterface.nestedvalueprocessor', ['class' => $class]));
            }
            $cast = new $class();
        }
        return $scope->node($input, function () use ($input, $nested, $target, $scope, $shape, $cast): ShapeResult {
            $result = [];
            $consumed = new SourceConsumption();
            $consumed->projection = true;
            $index = 0;
            $safe = $scope->hasListIndices($input);
            foreach ($input as $key => $raw) {
                $location = $scope->location()->descend([$key], $safe);
                $segments = $nested->each === null ? [] : explode('.', $nested->each);
                $value = $raw;
                if ($nested->each !== null) {
                    $selected = ArrayPath::getByPathWithStatus($raw, $nested->each);
                    $value = $selected->value;
                    $location = $location->descend(
                        $segments,
                        kind: $selected->isMissing() ? SourcePathKind::Expected : SourcePathKind::Resolved,
                    );
                }
                try {
                    $processed = $scope->at($location, function () use ($value, $target, $shape, $cast, $scope): ShapeResult {
                        if ($cast !== null) {
                            $value = $scope->cast($cast, $value);
                        }
                        $operation = function () use ($value, $target, $shape, $scope): ShapeResult {
                            if ($shape !== null) {
                                return $this->values->transform($value, $shape, new RulePolicy(), $scope);
                            }
                            if ($target !== null && class_exists($target)) {
                                if ($value instanceof $target) {
                                    return new ShapeResult($value, SourceConsumption::all());
                                }
                                if ($target === Base64File::class && is_string($value)) {
                                    return new ShapeResult(new Base64File($value), SourceConsumption::all());
                                }
                                if (!is_array($value) && !is_object($value)) {
                                    throw HydrationException::invalidValue('unexpected_response_shape', $target, get_debug_type($value));
                                }
                                return new ShapeResult($scope->hydrateDto($value, $target), SourceConsumption::all());
                            }
                            return new ShapeResult($value, SourceConsumption::all());
                        };
                        return $cast === null ? $operation() : $scope->boundary($operation);
                    }, [$key, ...$segments]);
                } catch (HydrationException $exception) {
                    throw $exception->prependPath('[' . $index . ']');
                }
                if ($processed->skip) {
                    $consumed->mergeAt([$key], SourceConsumption::all());
                } else {
                    $consumed->mergeAt([$key, ...$segments], $cast === null ? $processed->consumed : SourceConsumption::all());
                    if ($shape !== null || ($target !== null && class_exists($target))) {
                        $result[] = $processed->value;
                    } else {
                        $result[$key] = $processed->value;
                    }
                }
                $index++;
            }
            return new ShapeResult($result, $consumed);
        });
    }
}
