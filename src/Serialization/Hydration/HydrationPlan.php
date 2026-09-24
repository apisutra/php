<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Hydration;

use ReflectionAttribute;
use ReflectionClass;
use ReflectionParameter;

/** @internal План конфигурации; объектные args существуют только в результате bind одного узла. */
final readonly class HydrationPlan
{
    /**
     * @param list<HydrationFieldPlan> $fields
     * @param ?list<array{name: string, reflection: ReflectionParameter, hasDefault: bool}> $constructor
     * @param array<int, array<string, ReflectionAttribute<object>>> $recipes
     * @param array<int, array<string, object|null>> $templates
     */
    public function __construct(
        public ReflectionClass $reflection,
        public array $fields,
        public ?array $constructor,
        public ?string $receiver,
        private array $recipes = [],
        private array $templates = [],
    ) {
    }

    public function bind(): self
    {
        if ($this->recipes === []) {
            return $this;
        }
        $fields = $this->fields;
        // Все рецепты узла выполняются до первого поля и разрешения живого профиля.
        foreach ($this->recipes as $index => $recipes) {
            $attributes = $this->templates[$index];
            foreach ($recipes as $key => $recipe) {
                $attributes[$key] = $recipe->newInstance();
            }
            $fields[$index] = $fields[$index]->withAttributes($attributes);
        }
        return new self($this->reflection, $fields, $this->constructor, $this->receiver);
    }
}
