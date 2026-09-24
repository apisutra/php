<?php

declare(strict_types=1);

namespace ApiSutra\Testing;

use ApiSutra\Diagnostics\RedactionPolicy;

final class FixtureRedactor
{
    public function __construct(private readonly RedactionPolicy $policy = new RedactionPolicy())
    {
    }

    /**
     * @param array<string, mixed> $payload
     * @param list<string> $secretFields
     * @return array<string, mixed>
     */
    public function redact(array $payload, ?Fixture $fixture = null, array $secretFields = []): array
    {
        $payload = $this->policy->withFields($secretFields)->context($payload, limitBodies: false);
        if ($fixture === null) {
            return $payload;
        }

        $headers = $fixture->sensitiveHeaders();
        $payload = $this->redactHeaders($payload, $headers);

        $payload = $this->redactJsonParameters($payload, $fixture->sensitiveJsonParameters());
        $payload = $this->redactRegexPatterns($payload, $fixture->sensitiveRegexPatterns());

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     * @return array<string, mixed>
     */
    private function redactHeaders(array $payload, array $headers): array
    {
        foreach (['request', 'response'] as $section) {
            if (!isset($payload[$section]['headers']) || !is_array($payload[$section]['headers'])) {
                continue;
            }

            foreach ($headers as $name => $replacement) {
                foreach ($payload[$section]['headers'] as $headerName => $headerValue) {
                    if (strcasecmp($headerName, $name) === 0) {
                        $payload[$section]['headers'][$headerName] = $replacement;
                    }
                }
            }
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string|callable> $rules
     * @return array<string, mixed>
     */
    private function redactJsonParameters(array $payload, array $rules): array
    {
        foreach (['request', 'response'] as $section) {
            if (!isset($payload[$section]['body']) || !is_array($payload[$section]['body'])) {
                continue;
            }

            $payload[$section]['body'] = $this->applyJsonRules($payload[$section]['body'], $rules);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $patterns
     * @return array<string, mixed>
     */
    private function redactRegexPatterns(array $payload, array $patterns): array
    {
        foreach (['request', 'response'] as $section) {
            if (!isset($payload[$section]['body']) || !is_string($payload[$section]['body'])) {
                continue;
            }

            $body = $payload[$section]['body'];
            foreach ($patterns as $pattern => $replacement) {
                $body = preg_replace($pattern, $replacement, $body) ?? $body;
            }
            $payload[$section]['body'] = $body;
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, string|callable> $rules
     * @return array<string, mixed>
     */
    private function applyJsonRules(array $data, array $rules): array
    {
        foreach ($data as $key => $value) {
            if (array_key_exists($key, $rules)) {
                $replacement = $rules[$key];
                $data[$key] = is_callable($replacement) ? $replacement() : $replacement;
                continue;
            }

            if (is_array($value)) {
                $data[$key] = $this->applyJsonRules($value, $rules);
            }
        }

        return $data;
    }
}
