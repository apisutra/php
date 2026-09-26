<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydratorInterface;
use ApiSutra\Serialization\Context\HydrationContext;
use ApiSutra\Serialization\Input\InputShapeGuard;
use ApiSutra\Serialization\Input\SourceShapeMap;
use ApiSutra\Serialization\Integration\HttpMappingAdapter;
use ApiSutra\Serialization\Traversal\TraversalState;
use ApiSutra\Serialization\Traversal\TraversalViolation;
use ApiSutra\Contracts\Interfaces\DataTransfer\DefaultValueProviderInterface;
use ApiSutra\Enums\DataTransfer\ValueState;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\HydrationException;
use ApiSutra\VO\Pipeline\PipelineContext;
use Closure;

final class HydrationScope
{
    private ?Closure $hydrateNode = null;
    private ?PipelineContext $pipelineContext = null;
    private bool $tracking = false;
    private readonly TraversalState $traversal;
    private SourceLocation $location;
    private ?SourceShapeMap $shape = null;
    private bool $jsonSourceKnown = false;

    /** @internal Область создаётся и привязывается гидратором для одного корневого вызова. */
    public function __construct(
        private readonly ?DtoHydratorInterface $dtoHydrator = null,
        private readonly bool $jsonShapeValidation = true,
    ) {
        $this->location = new SourceLocation();
        $this->traversal = new TraversalState();
    }

    /**
     * @internal
     * @param Closure(array|object, string, self): object $hydrate
     */
    public static function bind(Closure $hydrate, ?PipelineContext $context, bool $tracking, ?DtoHydratorInterface $hydrator = null, bool $jsonShapeValidation = true): self
    {
        $scope = new self($hydrator, $jsonShapeValidation);
        $scope->hydrateNode = $hydrate;
        $scope->pipelineContext = $context;
        $scope->tracking = $tracking;
        if ($context?->hydrationSourceTransformed) {
            $scope->location = $scope->location->boundary();
        }
        return $scope;
    }

    public function hydrate(array|object $data, string $class): object
    {
        return $this->boundary(fn (): object => $this->hydrateDto($data, $class));
    }

    /** @return list<object> */
    public function hydrateCollection(array $items, string $class): array
    {
        return $this->boundary(fn (): array => $this->node($items, function () use ($items, $class): array {
            $result = [];
            foreach ($items as $item) {
                try {
                    if (!is_array($item) && !is_object($item)) {
                        throw HydrationException::invalidValue('unexpected_response_shape', $class, get_debug_type($item));
                    }
                    $result[] = $this->hydrateDto($item, $class);
                } catch (HydrationException $exception) {
                    throw $exception->prependPath('[' . count($result) . ']');
                }
            }
            return $result;
        }));
    }

    public function context(): ?PipelineContext
    {
        return $this->pipelineContext;
    }

    /** @internal Выбор текущего графа не меняется при вложенных преобразованиях. */
    public function hydrator(): ?DtoHydratorInterface
    {
        return $this->dtoHydrator;
    }

    /** @internal Вход ядра сохраняет известное происхождение дочернего узла. */
    public function hydrateDto(mixed $data, string $class, bool $emptyListAsObject = false): object
    {
        if ($this->hydrateNode === null) {
            throw new ConfigurationException(new Message('serialization.hydrationscope_must_be_created_by_a_hydrator'));
        }
        InputShapeGuard::assert($data, InputShape::Object, source: $this->shape($data), emptyListAsObject: $emptyListAsObject);
        return ($this->hydrateNode)($data, $class, $this);
    }

    /** @internal */
    public function shape(mixed $value): ?InputShape
    {
        return SourceShapeMap::kindOf($value, $this->shape, $this->jsonSourceKnown);
    }

    /** @internal Числовое имя свойства JSON object не является безопасным индексом. */
    public function hasListIndices(array $value): bool
    {
        return $this->jsonSourceKnown ? $this->shape($value) === InputShape::List
            : $this->jsonShapeValidation && array_is_list($value);
    }

    /**
     * @internal
     * @template T
     * @param Closure(): T $operation
     * @return T
     */
    public function withShape(?SourceShapeMap $shape, Closure $operation, bool $jsonSourceKnown = false): mixed
    {
        $previous = $this->shape;
        $previousKnown = $this->jsonSourceKnown;
        $previousTracking = $this->tracking;
        $this->shape = $this->jsonShapeValidation ? $shape : null;
        $this->jsonSourceKnown = $this->jsonShapeValidation && $jsonSourceKnown;
        $this->tracking = $previousTracking || $this->jsonSourceKnown;
        try {
            return $operation();
        } catch (HydrationException $exception) {
            throw $this->annotate($exception);
        } finally {
            $this->shape = $previous;
            $this->jsonSourceKnown = $previousKnown;
            $this->tracking = $previousTracking;
        }
    }

    /**
     * @internal
     * @template T
     * @param list<int|string> $segments
     * @param Closure(): T $operation
     * @return T
     */
    public function descend(array $segments, Closure $operation, bool $safe = true, SourcePathKind $kind = SourcePathKind::Resolved): mixed
    {
        return $this->at($this->location->descend($segments, $safe, $kind), $operation, $segments);
    }

    /**
     * @internal
     * Активация диагностики принадлежит узлу и не создаёт отдельный рекурсивный вызов.
     * @template T
     * @param Closure(): T $operation
     * @return T
     */
    public function node(array|object $data, Closure $operation, bool $tracking = false): mixed
    {
        $id = is_object($data) ? spl_object_id($data) : null;
        $previousTracking = $this->tracking;
        $this->tracking = $previousTracking || $tracking;
        $violation = $this->traversal->enterNode($id);
        try {
            if ($violation === TraversalViolation::Depth) {
                throw HydrationException::invalidValue('hydration_depth_exceeded', 'depth <= 512', 'deeper');
            }
            if ($violation === TraversalViolation::Cycle) {
                throw HydrationException::invalidValue('cyclic_hydration_input', 'acyclic input', 'cycle');
            }
            return $operation();
        } catch (HydrationException $exception) {
            throw $this->annotate($exception);
        } finally {
            if ($violation === null) {
                $this->traversal->leaveNode($id);
            }
            $this->tracking = $previousTracking;
        }
    }

    /** @internal */
    public function location(): SourceLocation
    {
        return $this->location;
    }

    /**
     * @internal
     * @template T
     * @param Closure(): T $operation
     * @param list<int|string>|null $segments
     * @return T
     */
    public function at(SourceLocation $location, Closure $operation, ?array $segments = null): mixed
    {
        $previous = $this->location;
        $this->location = $location;
        try {
            return $segments === null ? $operation() : $this->withShape($this->shape?->select($segments), $operation, $this->jsonSourceKnown);
        } catch (HydrationException $exception) {
            throw $this->annotate($exception);
        } finally {
            $this->location = $previous;
        }
    }

    /**
     * @internal
     * @template T
     * @param Closure(): T $operation
     * @return T
     */
    public function boundary(Closure $operation, bool $preserveShape = false): mixed
    {
        $location = $this->location->boundary();
        try {
            return $this->at($location, fn (): mixed => $this->withShape($preserveShape ? $this->shape : null, $operation, $preserveShape && $this->jsonSourceKnown));
        } catch (HydrationException $exception) {
            // Самостоятельный гидратор внутри обработчика не знает происхождения его входа.
            if (($this->tracking || $exception->sourcePathKind !== null) && $exception->sourcePathKind !== SourcePathKind::Boundary) {
                throw $exception->withSource($location);
            }
            throw $exception;
        }
    }

    /** @internal */
    public function annotate(HydrationException $exception): HydrationException
    {
        return $this->tracking && $exception->sourcePathKind === null
            ? $exception->withSource($this->location)
            : $exception;
    }

    /** @internal Одинаковый вызов обработчика для атрибута, профиля и внешнего descriptor. */
    public function cast(HydrationCastInterface $cast, mixed $value): mixed
    {
        return $this->boundary(fn (): mixed => $this->invoke(
            fn (HydrationContext $context): mixed => $cast->hydrate($value, $context),
        ));
    }

    /** @internal Встроенные преобразования сохраняют известное происхождение. */
    public function invoke(Closure $operation): mixed
    {
        return HydrationContext::invoke($this, HttpMappingAdapter::extensions($this->pipelineContext), $operation);
    }

    /**
     * @internal
     * @param array<string, mixed> $source
     */
    public function provide(DefaultValueProviderInterface $provider, mixed $value, ValueState $state, array $source): mixed
    {
        return $this->boundary(fn (): mixed => $this->invoke(
            fn (HydrationContext $context): mixed => $provider->resolve($value, $state, $source, $context),
        ));
    }
}
