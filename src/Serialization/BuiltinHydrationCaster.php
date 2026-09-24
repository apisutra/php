<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Localization\Message;
use ApiSutra\Attributes\DataTransfer\Cast as CastAttribute;
use ApiSutra\Attributes\DataTransfer\DateTimeFrom;
use ApiSutra\Casts\DateTimeCast;
use ApiSutra\Casts\EnumCast;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Serialization\VO\ResolvedDtoHydration;
use ApiSutra\Serialization\Rules\HydrationScope;
use ApiSutra\Serialization\Rules\RulePolicy;
use ApiSutra\Serialization\Rules\ScalarPolicy;
use ApiSutra\VO\Files\Base64File;
use ApiSutra\VO\Pipeline\PipelineContext;
use Closure;
use ApiSutra\Serialization\Context\HydrationContext;
use DateTimeInterface;
use ReflectionProperty;
use UnitEnum;

final readonly class BuiltinHydrationCaster
{
    private Closure $dtoHydrator;
    private SafeScalarHydrationCaster $safeScalarHydrationCaster;

    /**
     * @param callable(mixed, string, ?PipelineContext): object $dtoHydrator
     */
    public function __construct(
        private HydrationTypeSelector $typeSelector,
        callable $dtoHydrator,
        ?SafeScalarHydrationCaster $safeScalarHydrationCaster = null,
    ) {
        $this->dtoHydrator = $dtoHydrator instanceof Closure
            ? $dtoHydrator
            : Closure::fromCallable($dtoHydrator);
        $this->safeScalarHydrationCaster = $safeScalarHydrationCaster ?? new SafeScalarHydrationCaster();
    }

    public function hydrate(
        mixed $value,
        ?CastAttribute $cast,
        ?DateTimeFrom $dateTimeFrom,
        ReflectionProperty $property,
        ResolvedDtoHydration $resolved,
        ?PipelineContext $context,
        ?HydrationScope $scope = null,
        ?RulePolicy $policy = null,
    ): mixed {
        if ($value === null) {
            return null;
        }

        $scope ??= HydrationScope::bind(
            fn (array|object $data, string $class): object => ($this->dtoHydrator)($data, $class, $context),
            $context,
            false,
        );
        if ($cast !== null) {
            if (!class_exists($cast->class) || !is_subclass_of($cast->class, HydrationCastInterface::class)) {
                throw new ConfigurationException(new Message('serialization.hydration_cast_for_must_implement_hydrationcastinterface', ['value0' => $cast->class, 'value1' => $property->getDeclaringClass()->getName(), 'value2' => $property->getName()]));
            }
            $castInstance = new $cast->class(...$cast->args);

            return $scope->cast($castInstance, $value);
        }

        $type = $this->typeSelector->resolveType($property, $value);
        if ($type === null) {
            return $value;
        }

        $registryCast = $resolved->casts->get($type);
        if ($registryCast !== null) {
            if (!$registryCast instanceof HydrationCastInterface) {
                throw new ConfigurationException(new Message('serialization.hydration_cast_for_must_implement_hydrationcastinterface.builtinhydrationcaster', ['value0' => $registryCast::class, 'value1' => $property->getName(), 'type' => $type]));
            }
            return $scope->cast($registryCast, $value);
        }
        $spec = $policy?->casts[$type] ?? null;
        if ($spec !== null) {
            $instance = new $spec->class(...$spec->args);
            return $scope->cast($instance, $value);
        }

        if ($type === 'int' && IntegerRange::overflows($value)) {
            throw HydrationException::invalidValue('integer_out_of_range', 'int', get_debug_type($value));
        }

        if ($policy?->scalars !== ScalarPolicy::Strict && $this->safeScalarHydrationCaster->canHydrate($type, $value)) {
            return $this->safeScalarHydrationCaster->hydrate($type, $value);
        }

        if (is_subclass_of($type, DateTimeInterface::class)) {
            $policy = $dateTimeFrom?->toPolicy($resolved->policy->dateTime) ?? $resolved->policy->dateTime;

            return $scope->invoke(fn (HydrationContext $context): mixed => DateTimeCast::fromHydrationPolicy($policy)->hydrate($value, $context));
        }

        if (enum_exists($type) || is_subclass_of($type, UnitEnum::class)) {
            return $scope->invoke(fn (HydrationContext $context): mixed => (new EnumCast($type))->hydrate($value, $context));
        }

        if (is_subclass_of($type, DtoInterface::class)) {
            if (!is_array($value) && !is_object($value)) {
                throw HydrationException::invalidValue('unexpected_response_shape', $type, get_debug_type($value));
            }
            return $scope->hydrateDto($value, $type);
        }

        if ($type === Base64File::class && is_string($value)) {
            return new Base64File($value);
        }

        return $value;
    }

    /** @internal Пользовательское преобразование отмечает границу происхождения результата. */
    public function usesCustomCast(
        mixed $value,
        ?CastAttribute $cast,
        ReflectionProperty $property,
        ResolvedDtoHydration $resolved,
        ?RulePolicy $policy,
    ): bool {
        if ($value === null) {
            return false;
        }
        $type = $this->typeSelector->resolveType($property, $value);
        return $cast !== null || $type !== null
            && ($resolved->casts->get($type) !== null || isset($policy?->casts[$type]));
    }
}
