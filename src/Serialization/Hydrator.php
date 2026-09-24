<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Contracts\Interfaces\Localization\LocalizableExceptionInterface;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydratorInterface;
use ApiSutra\Exceptions\ControlFlow\ControlFlowException;
use ApiSutra\Exceptions\ControlFlow\ExecutorContractViolation;
use ApiSutra\Exceptions\Transport\ExecutionDeadlineException;
use ApiSutra\Serialization\Context\HydrationContext;
use Throwable;
use ApiSutra\Localization\Message;
use ApiSutra\Config\LocalizationConfig;
use ApiSutra\Attributes\AttributeMetadataCache;
use ApiSutra\Casts\CastRegistry;
use ApiSutra\Config\HydrationConfig;
use ApiSutra\Config\DtoHydrationPolicy;
use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\Enums\DataTransfer\EmptyStringBehavior;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\Metadata\MetadataCatalog;
use ApiSutra\Serialization\Hydration\HydrationCollections;
use ApiSutra\Serialization\Hydration\HydrationPlanCompiler;
use ApiSutra\Serialization\Hydration\HydrationFieldExecutor;
use ApiSutra\Serialization\Hydration\HydrationObjectFactory;
use ApiSutra\Serialization\Rules\HydrationRules;
use ApiSutra\Serialization\Rules\HydrationScope;
use ApiSutra\Serialization\Rules\SourceConsumption;
use ApiSutra\Serialization\Rules\SourcePathKind;
use ApiSutra\Serialization\Rules\RuleSetCompiler;
use ApiSutra\Serialization\VO\ResolvedDtoHydration;
use ApiSutra\VO\Pipeline\PipelineContext;
use JsonSerializable;
use ReflectionClass;

final class Hydrator
{
    private static ?self $default = null;
    private readonly RuleSetCompiler $descriptions;
    private readonly HydrationPlanCompiler $plans;
    private readonly HydrationFieldExecutor $fields;
    private readonly HydrationObjectFactory $objects;

    /** $casts сохранён для совместимости; источники casts задают профиль и декларации DTO. */
    public function __construct(
        private readonly CastRegistry $casts,
        private readonly ?AttributeMetadataCache $cache = null,
        private readonly ?DtoHydrationProfileResolver $profileResolver = null,
        private readonly ?HydrationConfig $config = null,
        private readonly LocalizationConfig $localization = new LocalizationConfig(),
    ) {
        try {
            $catalog = $cache?->catalog() ?? new MetadataCatalog();
            $this->descriptions = new RuleSetCompiler($config, $catalog);
            $this->plans = new HydrationPlanCompiler($cache, $catalog);
            $this->objects = new HydrationObjectFactory();
            $this->fields = new HydrationFieldExecutor(new BuiltinHydrationCaster(
                typeSelector: new HydrationTypeSelector(),
                dtoHydrator: fn (mixed $nestedValue, string $dtoClass, ?PipelineContext $nestedContext): object
                    => $this->hydrate($nestedValue, $dtoClass, $nestedContext),
            ));
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($this->localization);
        }
    }

    /**
     * Синглтон для DTO::from() без явного контекста.
     * Переданный глобальный registry не участвует в гидратации DTO.
     */
    public static function default(): self
    {
        return self::$default ??= new self(
            CastRegistry::global(),
        );
    }

    /** @internal Единое описание DTO для входящего и исходящего пути клиента. */
    public function descriptions(): RuleSetCompiler
    {
        return $this->descriptions;
    }

    /** @internal Возможности объявленного DTO для ошибок до начала гидратации. */
    public function tracksSource(?string $class): bool
    {
        return $this->config !== null || $class !== null && $this->descriptions->hasCapabilities($class);
    }

    public static function forRules(HydrationRules $rules): self
    {
        return self::forConfig(new HydrationConfig(rules: $rules));
    }

    public static function forConfig(HydrationConfig $config, LocalizationConfig $localization = new LocalizationConfig()): self
    {
        return new self(new CastRegistry(), new AttributeMetadataCache(), config: $config, localization: $localization);
    }

    public function hydrate(
        array|object $data,
        string $dtoClass,
        ?PipelineContext $context = null,
        DtoHydratorInterface|false|null $hydrator = null,
    ): object {
        try {
            return $this->scope($context, $hydrator)->hydrateDto($data, $dtoClass);
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($context?->config->localization ?? $this->localization);
        }
    }

    private function scope(?PipelineContext $context, DtoHydratorInterface|false|null $hydrator): HydrationScope
    {
        $selected = $hydrator === false ? null : ($hydrator ?? $this->config?->hydrator);
        return HydrationScope::bind(
            fn (array|object $data, string $class, HydrationScope $scope): object
                => $this->hydrateNode($data, $class, $scope),
            $context,
            $this->config !== null || $selected !== null,
            $selected,
        );
    }

    private function hydrateNode(array|object $data, string $dtoClass, HydrationScope $scope): object
    {
        $hydrator = $scope->hydrator();
        if ($hydrator !== null) {
            if (!class_exists($dtoClass) || (new ReflectionClass($dtoClass))->isAbstract() || enum_exists($dtoClass)) {
                throw new ConfigurationException(new Message('serialization.dto_class_is_unavailable_for_hydration', ['dtoClass' => $dtoClass]));
            }
            try {
                $supported = $hydrator->supports($dtoClass);
            } catch (ControlFlowException | ExecutionDeadlineException | ExecutorContractViolation $exception) {
                throw $exception;
            } catch (Throwable $exception) {
                throw $scope->annotate(HydrationException::invalidValue('custom_hydrator_failed', $dtoClass, 'exception', previous: $exception));
            }
            if ($supported) {
                return $scope->node($data, fn (): object => $scope->boundary(
                    fn (): object => $this->hydrateCustom($hydrator, $data, $dtoClass, $scope),
                ));
            }
        }
        return $scope->node(
            $data,
            function () use ($data, $dtoClass, $scope): object {
                $boundary = is_subclass_of($dtoClass, ResponseDtoInterface::class)
                    || is_object($data) && (method_exists($data, 'toArray') || $data instanceof JsonSerializable);
                if ($boundary) {
                    return $scope->boundary(fn (): object => $this->hydrateObject($data, $dtoClass, $scope));
                }
                return $this->hydrateObject($data, $dtoClass, $scope);
            },
            tracking: $hydrator !== null || $this->descriptions->hasCapabilities($dtoClass),
        );
    }

    private function hydrateCustom(DtoHydratorInterface $hydrator, array|object $data, string $dtoClass, HydrationScope $scope): object
    {
        try {
            $result = $scope->invoke(fn (HydrationContext $context): object => $hydrator->hydrate($data, $dtoClass, $context));
        } catch (HydrationException | ControlFlowException | ExecutionDeadlineException | ExecutorContractViolation $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw HydrationException::invalidValue('custom_hydrator_failed', $dtoClass, 'exception', previous: $exception);
        }
        if (!$result instanceof $dtoClass) {
            throw HydrationException::invalidValue('custom_hydrator_type_mismatch', $dtoClass, get_debug_type($result));
        }
        return $result;
    }

    private function hydrateObject(array|object $data, string $dtoClass, HydrationScope $scope): object
    {
        $context = $scope->context();
        if (!class_exists($dtoClass) || !(new ReflectionClass($dtoClass))->isInstantiable()) {
            throw new ConfigurationException(new Message('serialization.dto_class_is_unavailable_for_hydration', ['dtoClass' => $dtoClass]));
        }
        $description = $scope->hydrator() !== null
            ? $this->descriptions->forHydrationNode($dtoClass)
            : $this->descriptions->forClass($dtoClass);
        $array = $this->normalizeData($data);

        if (is_subclass_of($dtoClass, ResponseDtoInterface::class)) {
            $array = $dtoClass::computed($array, $context);
        }

        $plan = $this->plans->bind($description);
        $resolvedHydration = $this->resolveHydration($dtoClass);
        $values = [];
        $constructorChecks = [];
        $origins = [];
        $consumed = new SourceConsumption();
        $receiver = $plan->receiver;

        foreach ($plan->fields as $fieldPlan) {
            $name = $fieldPlan->name;
            if ($name === $receiver) {
                continue;
            }
            $field = $this->fields->hydrate($array, $fieldPlan, $resolvedHydration, $scope);
            if ($fieldPlan->constructorValue) {
                $constructorChecks[$name] = ['property' => $fieldPlan->property, 'input' => $field];
            }
            $origins[$name] = $field->location;
            $consumed->mergeAt($field->segments, $field->consumed);
            if ($field->state !== ValueState::Missing) {
                $values[$name] = $field->value;
            }
        }
        if ($receiver !== null) {
            [$keep, $rest] = $consumed->remainder($array);
            $values[$receiver] = $keep ? $rest : [];
        }

        try {
            return $this->objects->create($plan, $values, $constructorChecks);
        } catch (HydrationException $exception) {
            $location = $origins[$exception->path ?? '']
                ?? $scope->location()->descend([$exception->path ?? ''], kind: SourcePathKind::Expected);
            return $scope->at($location, static function () use ($exception): never {
                throw $exception;
            });
        }
    }

    /** @return array<int, object> */
    public function hydrateCollection(
        array $items,
        string $dtoClass,
        ?PipelineContext $context = null,
        DtoHydratorInterface|false|null $hydrator = null,
    ): array {
        try {
            $scope = $this->scope($context, $hydrator);
            return $scope->node($items, function () use ($items, $dtoClass, $scope): array {
                $result = [];
                foreach ($items as $key => $item) {
                    $result[] = $scope->at(
                        $scope->location()->descend([$key], array_is_list($items)),
                        fn (): object => HydrationCollections::item($item, $dtoClass, count($result), $scope),
                    );
                }
                return $result;
            });
        } catch (LocalizableExceptionInterface $exception) {
            throw $exception->localized($context?->config->localization ?? $this->localization);
        }
    }

    private function normalizeData(array|object $data): array
    {
        if (is_array($data)) {
            return $data;
        }

        if (method_exists($data, 'toArray')) {
            return (array) $data->toArray();
        }

        if ($data instanceof JsonSerializable) {
            return (array) $data->jsonSerialize();
        }

        return (array) $data;
    }

    private function resolveHydration(string $dtoClass): ResolvedDtoHydration
    {
        $resolver = $this->profileResolver ?? new DtoHydrationProfileResolver();

        return $resolver->resolveForDto($dtoClass, new DtoHydrationPolicy(
            namingStrategy: $this->config->policy->naming ?? NamingStrategy::None,
            dateTime: $this->config?->policy?->dateTime,
            emptyStringBehavior: $this->config->policy->emptyString ?? EmptyStringBehavior::Keep,
        ));
    }
}
