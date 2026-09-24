<?php

declare(strict_types=1);

namespace ApiSutra\Serialization\Plan;

use ApiSutra\Attributes\DataTransfer\Cast;
use ApiSutra\Enums\Http\HttpMethod;
use ApiSutra\Enums\Request\RequestUnmappedTarget;
use ReflectionAttribute;

/** @internal HTTP-размещение отделено от преобразования значения и от DTO-плана. */
final readonly class RequestPartsPlan
{
    /**
     * @param list<RequestFieldPlan> $fields
     * @param array<int, ReflectionAttribute<Cast>> $castRecipes
     */
    public function __construct(
        public array $fields,
        public ?string $bodyRoot,
        private RequestUnmappedTarget $unmapped,
        private array $castRecipes = [],
    ) {
    }

    public function unmappedTarget(HttpMethod $method): RequestUnmappedTarget
    {
        return $this->unmapped === RequestUnmappedTarget::Convention
            ? ($method->isQueryMethod() ? RequestUnmappedTarget::Query : RequestUnmappedTarget::Body)
            : $this->unmapped;
    }

    public function bind(): self
    {
        if ($this->castRecipes === []) {
            return $this;
        }
        $fields = $this->fields;
        // Все args создаются до чтения значений, даже у Ignore/null/Header/Path/File.
        foreach ($this->castRecipes as $index => $recipe) {
            $fields[$index] = $fields[$index]->withCast($recipe->newInstance());
        }
        return new self($fields, $this->bodyRoot, $this->unmapped);
    }
}
