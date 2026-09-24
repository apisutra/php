<?php

declare(strict_types=1);

namespace ApiSutra\Serialization;

use ApiSutra\Localization\Message;
use ApiSutra\Enums\Http\QueryArrayFormat;
use ApiSutra\Enums\Serialization\BooleanFormat;
use ApiSutra\Exceptions\Configuration\ConfigurationException;
use ApiSutra\Exceptions\Serialization\SerializationException;
use ApiSutra\VO\Pipeline\PipelineContext;

final class RequestUrlBuilder
{
    /**
     * @return array<string, mixed>
     */
    public function extractPathParams(string $endpoint): array
    {
        $this->assertRelativeEndpoint($endpoint);
        [$path] = UrlQuery::split($endpoint);
        preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $path, $matches);
        $params = [];
        foreach ($matches[1] ?? [] as $name) {
            $params[$name] = null;
        }

        return $params;
    }

    /**
     * @param array<string, mixed> $placeholders
     * @param array<string, array{value: mixed, format: ?QueryArrayFormat}> $query
     */
    public function buildUrl(
        string $baseUrl,
        string $endpoint,
        array $placeholders,
        array $query,
        ?PipelineContext $context,
    ): string {
        $this->assertRelativeEndpoint($endpoint);
        [$basePath, $baseQuery] = UrlQuery::split($baseUrl);
        [$endpointPath, $endpointQuery] = UrlQuery::split($endpoint);
        $endpointPath = $this->applyPathParams($endpointPath, $placeholders);
        $url = rtrim($basePath, '/') . '/' . ltrim($endpointPath, '/');

        return UrlQuery::append($url, $baseQuery, $endpointQuery, $this->buildQueryString(
            $query,
            $context?->config->queryArrayFormat ?? QueryArrayFormat::Brackets,
            $context?->config->textBooleanFormat ?? BooleanFormat::Numeric,
        ));
    }

    private function assertRelativeEndpoint(string $endpoint): void
    {
        $endpoint = explode('#', $endpoint, 2)[0];
        if (
            str_starts_with($endpoint, '//') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $endpoint)
            || preg_match('/[\\x00-\\x20\\x7f]/', $endpoint) || str_contains($endpoint, chr(92))
        ) {
            throw new ConfigurationException(new Message('serialization.endpoint_must_be_a_relative_uri_without_control_characters'));
        }
        [$path] = UrlQuery::split($endpoint);
        foreach (explode('/', $path) as $segment) {
            if (in_array(rawurldecode($segment), ['.', '..'], true)) {
                throw new ConfigurationException(new Message('serialization.endpoint_segments_and_are_not_supported'));
            }
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    private function applyPathParams(string $endpoint, array $params): string
    {
        $path = preg_replace_callback('/\{([a-zA-Z0-9_]+)\}/', function (array $match) use ($params): string {
            $value = $params[$match[1]] ?? null;
            if ($value === null || !is_scalar($value) || (is_float($value) && !is_finite($value))) {
                throw new SerializationException(new Message('serialization.missing_or_invalid_path_parameter', ['value0' => $match[1]]));
            }
            $value = (string) $value;
            if ($value === '' || $value === '.' || $value === '..') {
                throw new SerializationException(new Message('serialization.path_parameter_cannot_be_empty_or', ['value0' => $match[1]]));
            }
            return rawurlencode($value);
        }, $endpoint);
        if ($path === null || strpbrk($path, '{}') !== false) {
            throw new SerializationException(new Message('serialization.invalid_or_unresolved_path_placeholder'));
        }
        return $path;
    }

    /**
     * @param array<string, array{value: mixed, format: ?QueryArrayFormat}> $query
     */
    private function buildQueryString(array $query, QueryArrayFormat $defaultFormat, BooleanFormat $booleanFormat): string
    {
        $parts = [];
        foreach ($query as $key => $data) {
            $value = $data['value'] ?? null;
            $format = $data['format'] ?? $defaultFormat;
            if ($value === null) {
                $parts[] = rawurlencode((string) $key) . '=';
                continue;
            }

            if (is_array($value)) {
                foreach ($this->formatArrayQuery((string) $key, $value, $format, $booleanFormat) as $pair) {
                    $parts[] = $pair;
                }
                continue;
            }

            $parts[] = rawurlencode((string) $key) . '=' . rawurlencode($this->queryScalar($value, $booleanFormat));
        }

        return implode('&', $parts);
    }

    /**
     * @param array<int|string, mixed> $values
     * @return array<int, string>
     */
    private function formatArrayQuery(string $key, array $values, QueryArrayFormat $format, BooleanFormat $booleanFormat): array
    {
        if (!array_is_list($values)) {
            throw new SerializationException(new Message('serialization.query_supports_only_flat_lists_use_an_explicit_cast'));
        }
        if ($values === []) {
            return [];
        }
        $values = array_map(fn (mixed $value): string => $this->queryScalar($value, $booleanFormat), $values);
        if ($format === QueryArrayFormat::Comma) {
            foreach ($values as $value) {
                if (str_contains($value, ',')) {
                    throw new SerializationException(new Message('serialization.comma_list_value_contains_the_delimiter_use_another_format'));
                }
            }
            return [rawurlencode($key) . '=' . rawurlencode(implode(',', $values))];
        }

        $parts = [];
        foreach ($values as $index => $value) {
            $name = match ($format) {
                QueryArrayFormat::Repeat => $key,
                QueryArrayFormat::Indices => $key . '[' . $index . ']',
                default => $key . '[]',
            };
            // Сохраняем существующий вид скобок для query-списков.
            $encodedKey = rawurlencode($key) . substr($name, strlen($key));
            $parts[] = $encodedKey . '=' . rawurlencode($value);
        }
        return $parts;
    }

    private function queryScalar(mixed $value, BooleanFormat $booleanFormat): string
    {
        return match (true) {
            $value === null => '',
            is_bool($value) => $booleanFormat->format($value),
            is_string($value), is_int($value) => (string) $value,
            is_float($value) && is_finite($value) => (string) $value,
            default => throw new SerializationException(new Message('serialization.query_expects_a_scalar_or_a_flat_list_of')),
        };
    }
}
