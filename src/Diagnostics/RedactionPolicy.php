<?php

declare(strict_types=1);

namespace ApiSutra\Diagnostics;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

/**
 * Маскирование диагностических копий; исходные запросы и потоки не изменяются.
 */
final readonly class RedactionPolicy
{
    private const array HEADERS = [
        'authorization', 'proxy-authorization', 'cookie', 'set-cookie',
        'x-api-key', 'api-key', 'x-auth-token', 'x-access-token',
    ];
    private const array FIELDS = [
        'password', 'token', 'secret', 'api_key', 'apikey',
        'client_secret', 'access_token', 'refresh_token',
    ];

    /**
     * @param list<string> $headers Дополнительные секретные заголовки.
     * @param list<string> $fields Дополнительные имена полей на любой глубине.
     * @param list<string> $paths Пути полей через точку; * соответствует одному уровню.
     */
    public function __construct(
        private array $headers = [],
        private array $fields = [],
        private array $paths = [],
        public int $maxBodyBytes = 65536,
    ) {
        if ($maxBodyBytes <= 0) {
            throw new ConfigurationException(new Message('diagnostics.diagnostic_body_limit_must_be_positive'));
        }
    }

    /** @param list<string> $fields */
    public function withFields(array $fields): self
    {
        return new self($this->headers, [...$this->fields, ...$fields], $this->paths, $this->maxBodyBytes);
    }

    /**
     * @param array<string, mixed> $headers
     * @return array<string, mixed>
     */
    public function headers(array $headers): array
    {
        $names = array_map('strtolower', [...self::HEADERS, ...$this->headers]);
        foreach ($headers as $name => $value) {
            if (in_array(strtolower($name), $names, true)) {
                $headers[$name] = is_array($value) ? array_fill(0, count($value), '***') : '***';
            }
        }

        return $headers;
    }

    public function data(mixed $data, string $path = ''): mixed
    {
        if (!is_array($data)) {
            return $data;
        }

        foreach ($data as $name => $value) {
            $field = (string) $name;
            $fieldPath = $path === '' ? $field : $path . '.' . $field;
            $data[$name] = $this->isSensitive($field, $fieldPath)
                ? '***'
                : $this->data($value, $fieldPath);
        }

        return $data;
    }

    /**
     * Query metadata сохраняет оболочку value/format.
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function query(array $query): array
    {
        foreach ($query as $name => $item) {
            if (is_array($item) && array_key_exists('value', $item)) {
                $item['value'] = $this->isSensitive((string) $name, (string) $name)
                    ? '***'
                    : $this->data($item['value'], (string) $name);
                $query[$name] = $item;
            } else {
                $query[$name] = $this->isSensitive((string) $name, (string) $name)
                    ? '***'
                    : $this->data($item, (string) $name);
            }
        }

        return $query;
    }

    public function url(string $url): string
    {
        // Обрабатываем строку без пересборки URI: повторяющиеся query сохраняются.
        $url = preg_replace('~(//)[^/?#]*@~', '$1***@', $url) ?? '[redacted-url]';
        $question = strpos($url, '?');
        if ($question === false) {
            return $url;
        }

        $fragment = strpos($url, '#', $question);
        $end = $fragment === false ? strlen($url) : $fragment;
        return substr($url, 0, $question + 1)
            . $this->form(substr($url, $question + 1, $end - $question - 1))
            . substr($url, $end);
    }

    public function body(?string $body, ?string $contentType = null): ?string
    {
        if ($body === null || trim($body) === '') {
            return $body;
        }

        $decoded = json_decode($body, true, 512, JSON_BIGINT_AS_STRING);
        if (json_last_error() === JSON_ERROR_NONE) {
            $encoded = json_encode($this->data($decoded), JSON_UNESCAPED_UNICODE);
            return is_string($encoded) ? $encoded : '[redacted-body]';
        }

        $type = strtolower($contentType ?? '');
        if (str_contains($type, 'json') || str_starts_with(ltrim($body), '{') || str_starts_with(ltrim($body), '[')) {
            return '[redacted-body]';
        }
        if (str_contains($type, 'application/x-www-form-urlencoded')) {
            return $this->form($body);
        }

        // Неструктурированный текст не объявляется очищенным от произвольных секретов.
        return $body;
    }

    /**
     * Маскирование стандартных полей диагностического представления.
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    public function context(array $context, bool $limitBodies = true): array
    {
        $headers = is_array($context['headers'] ?? null) ? $context['headers'] : [];
        $type = null;
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'Content-Type') === 0) {
                $type = is_array($value) ? ($value[0] ?? null) : $value;
            }
        }
        $raw = $context['bodyRaw'] ?? $context['body'] ?? null;
        $size = is_string($raw) ? strlen($raw) : null;
        if ($limitBodies && $size !== null && $size > $this->maxBodyBytes) {
            foreach (['body', 'bodyRaw', 'form'] as $field) {
                if (array_key_exists($field, $context)) {
                    $context[$field] = null;
                }
            }
            $context['bodyOmitted'] = true;
            $context['bodyOmissionReason'] = 'body_size_limit';
            $context['bodySize'] = $size;
        }
        foreach ($context as $name => $value) {
            $key = strtolower((string) $name);
            if ($key === 'headers' && is_array($value)) {
                $context[$name] = $this->headers($value);
            } elseif (in_array($key, ['url', 'uri'], true) && is_string($value)) {
                $context[$name] = $this->url($value);
            } elseif (in_array($key, ['body', 'bodyraw'], true)) {
                $encoded = is_array($value) && $limitBodies ? json_encode($value) : null;
                if (is_string($encoded) && strlen($encoded) > $this->maxBodyBytes) {
                    $context[$name] = null;
                    $context['bodyOmitted'] = true;
                    $context['bodyOmissionReason'] = 'body_size_limit';
                    $context['bodySize'] = strlen($encoded);
                } else {
                    $context[$name] = is_string($value) ? $this->body($value, $type) : $this->data($value);
                }
            } elseif ($key === 'query' && is_array($value)) {
                $context[$name] = $this->query($value);
            } elseif ($this->isSensitive((string) $name, (string) $name)) {
                $context[$name] = '***';
            } elseif (is_array($value)) {
                $context[$name] = $this->context($value, $limitBodies);
            }
        }

        return $context;
    }

    private function form(string $query): string
    {
        $parts = explode('&', $query);
        foreach ($parts as &$part) {
            [$name] = explode('=', $part, 2);
            $decoded = urldecode($name);
            $path = trim(str_replace(['[', ']'], ['.', ''], $decoded), '.');
            $segments = explode('.', $path);
            $sensitive = $this->isSensitive($decoded, $path);
            foreach ($segments as $segment) {
                $sensitive = $sensitive || $this->isSensitive($segment, $path);
            }
            if ($sensitive) {
                $part = $name . '=***';
            }
        }
        unset($part);

        return implode('&', $parts);
    }

    private function isSensitive(string $field, string $path): bool
    {
        $names = array_map('strtolower', [...self::FIELDS, ...$this->fields]);
        if (in_array(strtolower(trim($field)), $names, true)) {
            return true;
        }
        foreach ($this->paths as $pattern) {
            $expression = str_replace('\\*', '[^.]+', preg_quote($pattern, '~'));
            if (preg_match('~^' . $expression . '$~iD', $path) === 1) {
                return true;
            }
        }

        return false;
    }
}
