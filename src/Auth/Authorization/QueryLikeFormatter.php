<?php

declare(strict_types=1);

namespace ApiSutra\Auth\Authorization;

use ApiSutra\Contracts\Interfaces\Auth\AuthorizationParamsFormatterInterface;
use Stringable;

final readonly class QueryLikeFormatter implements AuthorizationParamsFormatterInterface
{
    public function __construct(
        private bool $encode = false,
        private bool $sortKeys = false,
    ) {
    }

    /**
     * @param array<string, string|int|float|bool|Stringable> $params
     */
    public function format(array $params): string
    {
        if ($this->sortKeys) {
            ksort($params);
        }

        $parts = [];
        foreach ($params as $key => $value) {
            $parts[] = $this->formatParam((string) $key, $value);
        }

        return implode('&', $parts);
    }

    private function formatParam(string $key, string|int|float|bool|Stringable $value): string
    {
        $key = $this->encode ? rawurlencode($key) : $key;
        $value = $this->stringifyValue($value);
        $value = $this->encode ? rawurlencode($value) : $value;

        return $key . '=' . $value;
    }

    private function stringifyValue(string|int|float|bool|Stringable $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return (string) $value;
    }
}
