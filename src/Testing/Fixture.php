<?php

declare(strict_types=1);

namespace ApiSutra\Testing;

abstract class Fixture
{
    public function name(): string
    {
        return $this->defineName();
    }

    /**
     * @return array<string, string>
     */
    public function sensitiveHeaders(): array
    {
        return $this->defineSensitiveHeaders();
    }

    /**
     * @return array<string, string|callable>
     */
    public function sensitiveJsonParameters(): array
    {
        return $this->defineSensitiveJsonParameters();
    }

    /**
     * @return array<string, string>
     */
    public function sensitiveRegexPatterns(): array
    {
        return $this->defineSensitiveRegexPatterns();
    }

    protected function defineName(): string
    {
        return static::class;
    }

    /**
     * @return array<string, string>
     */
    protected function defineSensitiveHeaders(): array
    {
        return [];
    }

    /**
     * @return array<string, string|callable>
     */
    protected function defineSensitiveJsonParameters(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    protected function defineSensitiveRegexPatterns(): array
    {
        return [];
    }
}
