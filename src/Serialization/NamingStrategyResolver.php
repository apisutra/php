<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Enums\Configuration\NamingStrategy;
use ApiSutra\VO\Pipeline\PipelineContext;

final readonly class NamingStrategyResolver
{
    public function resolve(string $name, ?PipelineContext $context): string
    {
        if ($context === null) {
            return $name;
        }

        return $this->resolveByStrategy($name, $context->config->namingStrategy);
    }

    public function resolveByStrategy(string $name, NamingStrategy $strategy): string
    {
        return match ($strategy) {
            NamingStrategy::SnakeCase => $this->toSnakeCase($name),
            NamingStrategy::None => $name,
        };
    }

    private function toSnakeCase(string $value): string
    {
        $result = preg_replace('/(?<!^)[A-Z]/', '_$0', $value) ?? $value;
        return strtolower($result);
    }
}
