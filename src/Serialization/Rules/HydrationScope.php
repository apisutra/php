<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\Casting\HydrationCastInterface;
use ApiSutra\Contracts\Interfaces\Serialization\DtoHydratorInterface;
use ApiSutra\Serialization\Context\HydrationContext;
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

    /** @internal Область создаётся и привязывается гидратором для одного корневого вызова. */
    public function __construct(private readonly ?DtoHydratorInterface $dtoHydrator = null)
    {
        $this->location = new SourceLocation();
        $this->traversal = new TraversalState();
    }

    /**
     * @internal
     * @param Closure(array|object, string, self): object $hydrate
     */
    public static function bind(Closure $hydrate, ?PipelineContext $context, bool $tracking, ?DtoHydratorInterface $hydrator = null): self
    {
        $scope = new self($hydrator);
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
    public function hydrateDto(array|object $data, string $class): object
    {
        if ($this->hydrateNode === null) {
            throw new ConfigurationException(new Message('serialization.hydrationscope_must_be_created_by_a_hydrator'));
        }
        return ($this->hydrateNode)($data, $class, $this);
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
     * @return T
     */
    public function at(SourceLocation $location, Closure $operation): mixed
    {
        $previous = $this->location;
        $this->location = $location;
        try {
            return $operation();
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
    public function boundary(Closure $operation): mixed
    {
        $location = $this->location->boundary();
        try {
            return $this->at($location, $operation);
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
