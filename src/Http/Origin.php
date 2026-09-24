<?php

declare(strict_types=1);

namespace ApiSutra\Http;

use ApiSutra\Localization\Message;
use ApiSutra\Exceptions\Configuration\ConfigurationException;

final class Origin
{
    public static function fromUrl(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = strtolower($parts['host'] ?? '');
        if (
            !in_array($scheme, ['http', 'https'], true) || $host === ''
            || preg_match('/[^a-z0-9.\-:\[\]]/', $host)
        ) {
            throw new ConfigurationException(new Message('http.url_must_contain_an_http_https_origin_with_an'));
        }
        if (str_starts_with($host, '[') && filter_var(trim($host, '[]'), FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) === false) {
            throw new ConfigurationException(new Message('http.invalid_ipv6_origin'));
        }
        if (str_starts_with($host, '[')) {
            $host = '[' . inet_ntop(inet_pton(trim($host, '[]'))) . ']';
        }
        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if ($port < 1 || $port > 65535) {
            throw new ConfigurationException(new Message('http.invalid_origin_port'));
        }
        return $scheme . '://' . $host . ':' . $port;
    }
}
