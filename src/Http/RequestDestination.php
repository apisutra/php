<?php

declare(strict_types=1);

namespace ApiSutra\Http;

use ApiSutra\Localization\Message;
use ApiSutra\Config\OriginPolicy;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

/** Назначение выбирается до добавления credentials и сохраняется на всё исполнение. */
final readonly class RequestDestination
{
    public string $origin;
    public bool $crossOrigin;

    public function __construct(
        public string $url,
        string $sourceUrl,
        public bool $preserveUrl,
    ) {
        $this->origin = Origin::fromUrl($url);
        $this->crossOrigin = $this->origin !== Origin::fromUrl($sourceUrl);
        if ($preserveUrl) {
            self::validateFullUrl($url);
        }
    }

    public static function isAbsolute(string $url): bool
    {
        return preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) === 1;
    }

    public static function validateFullUrl(string $url): void
    {
        $parts = parse_url($url);
        if (
            $parts === false || isset($parts['user']) || isset($parts['pass'])
            || preg_match('/[\x00-\x20\x7f-\xff<>"{}|^`\\\\]/', $url)
            || preg_match('/%(?![a-f0-9]{2})/i', $url)
        ) {
            throw new ConfigurationException(new Message('http.invalid_absolute_url_an_encoded_http_https_url_without'));
        }
        Origin::fromUrl($url);
    }

    public function requiresIsolation(): bool
    {
        return $this->preserveUrl || $this->crossOrigin;
    }

    public function assertCredentialsAllowed(OriginPolicy $policy): void
    {
        if ($this->crossOrigin && !$policy->allows($this->origin)) {
            throw new ConfigurationException(new Message('http.credentials_cannot_be_sent_to_the_target_origin'));
        }
    }

    public function assertUrl(string $url): void
    {
        if (Origin::fromUrl($url) !== $this->origin || ($this->preserveUrl && $url !== $this->url)) {
            throw new ConfigurationException(new Message('http.request_destination_or_absolute_url_changed_after_preparation'));
        }
    }

    public function requestTarget(): string
    {
        $authorityEnd = strpos($this->url, '/', strpos($this->url, '://') + 3);
        $queryStart = strpos($this->url, '?');
        if ($authorityEnd === false || ($queryStart !== false && $queryStart < $authorityEnd)) {
            return '/' . ($queryStart === false ? '' : substr($this->url, $queryStart));
        }
        return substr($this->url, $authorityEnd);
    }

    public function diagnosticUrl(): string
    {
        return $this->origin . '/[redacted]';
    }

    /** Маскирует известные ссылки также при отражении адреса в теле ответа/фикстуре. */
    public function redactReferences(mixed $value): mixed
    {
        if (!$this->preserveUrl) {
            return $value;
        }
        if ($value instanceof Message) {
            return new Message($value->key, $this->redactReferences($value->parameters));
        }
        if (is_array($value)) {
            return array_map(fn (mixed $item): mixed => $this->redactReferences($item), $value);
        }
        if (is_string($value)) {
            $value = str_replace([$this->url, str_replace('/', '\\/', $this->url)], $this->diagnosticUrl(), $value);
            $target = $this->requestTarget();
            if (strlen($target) > 1) {
                $value = str_replace([$target, str_replace('/', '\\/', $target)], '/[redacted]', $value);
            }
        }
        return $value;
    }
}
