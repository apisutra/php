<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Rules;

use ApiSutra\Localization\Message;
use ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use ApiSutra\Exceptions\Serialization\SerializationException;
use Closure;
use ApiSutra\Serialization\Traversal\TraversalState;
use DateTimeInterface;
use JsonSerializable;
use Stringable;
use UnitEnum;

/** @internal Исключает receiver, сохраняя представление остальных plain-значений для JSON. */
final readonly class ReceiverOutput
{
    public function __construct(private RuleSetCompiler $descriptions)
    {
    }

    public function receiverFor(string $class): ?string
    {
        return $this->descriptions->receiverFor($class);
    }

    public function contains(mixed $value): bool
    {
        $ancestors = [];
        return $this->scan($value, $ancestors, 0);
    }

    /** @param callable(object): array<string, mixed> $dtoSerializer */
    public function project(mixed $value, callable $dtoSerializer): mixed
    {
        $ancestors = [];
        return $this->rewrite($value, $dtoSerializer, $ancestors, 0)[1];
    }

    /** @param array<int, true> $ancestors */
    private function scan(mixed $value, array &$ancestors, int $depth): bool
    {
        if (!is_array($value) && !is_object($value)) {
            return false;
        }
        if (is_object($value) && $this->receiverFor($value::class) !== null) {
            return true;
        }
        if ($this->opaque($value)) {
            return false;
        }
        $id = $this->guard($value, $ancestors, $depth);
        try {
            foreach (is_object($value) ? get_object_vars($value) : $value as $child) {
                if ($this->scan($child, $ancestors, $depth + 1)) {
                    return true;
                }
            }
            return false;
        } finally {
            if ($id !== null) {
                unset($ancestors[$id]);
            }
        }
    }

    /**
     * @param callable(object): array<string, mixed> $dtoSerializer
     * @param array<int, true> $ancestors
     * @return array{bool, mixed}
     */
    private function rewrite(mixed $value, callable $dtoSerializer, array &$ancestors, int $depth): array
    {
        if (is_object($value)) {
            $this->receiverFor($value::class);
        }
        if ((!is_array($value) && !is_object($value)) || $this->opaque($value)) {
            return [false, $value];
        }
        $id = $this->guard($value, $ancestors, $depth);
        try {
            if ($value instanceof DtoInterface) {
                return $this->contains($value) ? [true, $dtoSerializer($value)] : [false, $value];
            }
            $receiver = is_object($value) ? $this->receiverFor($value::class) : null;
            $fields = is_object($value) ? get_object_vars($value) : $value;
            $changed = $receiver !== null;
            if ($receiver !== null) {
                unset($fields[$receiver]);
            }
            foreach ($fields as $key => $child) {
                [$childChanged, $rewritten] = $this->rewrite($child, $dtoSerializer, $ancestors, $depth + 1);
                if ($childChanged) {
                    $fields[$key] = $rewritten;
                    $changed = true;
                }
            }
            if (!$changed) {
                return [false, $value];
            }
            // JSON-объект остаётся объектом при пустых или числовых свойствах.
            if (is_object($value) && ($receiver === null || array_is_list($fields))) {
                return [true, (object) $fields];
            }
            return [true, $fields];
        } finally {
            if ($id !== null) {
                unset($ancestors[$id]);
            }
        }
    }

    private function opaque(mixed $value): bool
    {
        if ($value instanceof DtoInterface) {
            return false;
        }
        return $value instanceof JsonSerializable || $value instanceof Stringable
            || $value instanceof DateTimeInterface || $value instanceof UnitEnum || $value instanceof Closure
            || is_object($value) && method_exists($value, 'toArray');
    }

    /**
     * @param array<int, true> $ancestors
     */
    private function guard(array|object $value, array &$ancestors, int $depth): ?int
    {
        $id = is_object($value) ? spl_object_id($value) : null;
        if ($depth >= TraversalState::MAX_DEPTH || $id !== null && isset($ancestors[$id])) {
            throw new SerializationException(new Message('serialization.circular_reference_or_outgoing_dto_depth_exceeded'));
        }
        if ($id !== null) {
            $ancestors[$id] = true;
        }
        return $id;
    }
}
