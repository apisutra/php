<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Localization\Message;
use BackedEnum;
use ApiSutra\Contracts\Interfaces\DataTransfer\DtoInterface;
use ApiSutra\Enums\Serialization\EnumOutput;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\SerializationException;
use Stringable;
use UnitEnum;
use Closure;
use ApiSutra\Serialization\Traversal\TraversalState;

final readonly class EnumSerializationHelper
{
    /**
     * @param array<int|string, mixed> $items
     * @param Closure(DtoInterface): array $dtoSerializer
     * @return array<int|string, mixed>
     */
    public function serializeArray(
        array $items,
        EnumOutput $output,
        bool $strictMode,
        Closure $dtoSerializer,
        int $depth = 0,
    ): array {
        if ($depth >= TraversalState::MAX_DEPTH) {
            throw new SerializationException(new Message('serialization.array_serialization_depth_exceeded_512_possible_circular_reference'));
        }
        $result = [];
        foreach ($items as $key => $item) {
            $result[$key] = $this->serializeValue($item, $output, $strictMode, $dtoSerializer, $depth + 1);
        }

        return $result;
    }

    /**
     * @param Closure(DtoInterface): array $dtoSerializer
     */
    public function serializeValue(
        mixed $value,
        EnumOutput $output,
        bool $strictMode,
        Closure $dtoSerializer,
        int $depth = 0,
    ): mixed {
        if ($value instanceof DtoInterface) {
            return $dtoSerializer($value);
        }

        if ($value instanceof UnitEnum) {
            return $this->serializeEnum($value, $output, $strictMode);
        }

        if (is_array($value)) {
            return $this->serializeArray($value, $output, $strictMode, $dtoSerializer, $depth);
        }

        return $value;
    }

    public function serializeEnum(
        UnitEnum $enum,
        EnumOutput $output,
        bool $strictMode,
    ): array|string|int {
        $value = $this->resolveValue($enum);

        return match ($output) {
            EnumOutput::Value => $value,
            EnumOutput::Name => $enum->name,
            EnumOutput::Object => [
                'value' => $value,
                'title' => $this->resolveTitle($enum, $value, $strictMode),
            ],
            EnumOutput::TitleValueString => $this->formatTitleValue($enum, $value, $strictMode),
        };
    }

    private function resolveValue(UnitEnum $enum): string|int
    {
        if ($enum instanceof BackedEnum) {
            return $enum->value;
        }

        return $enum->name;
    }

    private function resolveTitle(UnitEnum $enum, string|int $fallback, bool $strictMode): string
    {
        if (!method_exists($enum, 'title')) {
            if ($strictMode) {
                throw new ConfigurationException(new Message('serialization.enum_must_have_a_title_method', ['value0' => $enum::class]));
            }

            return (string) $fallback;
        }

        $title = $enum->title();
        if (is_string($title)) {
            return $title;
        }

        if ($title instanceof Stringable) {
            return (string) $title;
        }

        if ($strictMode) {
            throw new ConfigurationException(new Message('serialization.enum_must_return_a_string_from_title', ['value0' => $enum::class]));
        }

        return (string) $fallback;
    }

    private function formatTitleValue(UnitEnum $enum, string|int $value, bool $strictMode): string
    {
        $title = $this->resolveTitle($enum, $value, $strictMode);

        return $title . '|' . (string) $value;
    }
}
