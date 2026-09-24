<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Plan;

use ApiSutra\Attributes\DataTransfer\Cast;
use ReflectionAttribute;

/** @internal В кеше нет объектных args, обработчиков, DTO и результатов профиля. */
final readonly class SerializationPlan
{
    /**
     * @param list<SerializationFieldPlan> $fields
     * @param array<int, ReflectionAttribute<Cast>> $castRecipes
     */
    public function __construct(private array $fields, private array $castRecipes)
    {
    }

    /** @return list<SerializationFieldPlan> */
    public function bind(): array
    {
        $fields = $this->fields;
        // Материализуем все args до первого чтения DTO, включая null и пропускаемые поля.
        foreach ($this->castRecipes as $index => $recipe) {
            $fields[$index] = $fields[$index]->withCast($recipe->newInstance());
        }
        return $fields;
    }
}
