<?php

declare(strict_types=1);

namespace ApiSutra\Auth\Authorization;

use ApiSutra\Contracts\Interfaces\Auth\AuthorizationParamsFormatterInterface;
use Stringable;

final readonly class QuotedCommaFormatter implements AuthorizationParamsFormatterInterface
{
    /**
     * @param array<string, string|int|float|bool|Stringable> $params
     */
    public function format(array $params): string
    {
        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $this->formatParam((string) $key, $value);
        }

        return implode(', ', $parts);
    }

    private function formatParam(string $key, string|int|float|bool|Stringable $value): string
    {
        $escaped = $this->escapeValue($this->stringifyValue($value));
        return $key . '="' . $escaped . '"';
    }

    private function stringifyValue(string|int|float|bool|Stringable $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }

    private function escapeValue(string $value): string
    {
        return str_replace(['\\', '"'], ['\\\\', '\\"'], $value);
    }
}
