<?php

declare(strict_types=1);

namespace ApiSutra\Auth;

use ApiSutra\VO\Http\PreparedRequest;

/** Отпечаток объявленных authenticator полей после подготовки запроса. */
final class CacheCredentialIdentity
{
    /** @param list<string> $headers */
    public static function forRequest(string $identity, ?PreparedRequest $request, array $headers, ?string $query = null): string
    {
        if ($request === null) {
            return $identity;
        }
        $selected = [];
        $names = array_map(strtolower(...), $headers);
        foreach (self::headersForCache($request) as $name => $value) {
            $name = strtolower($name);
            if (in_array($name, $names, true)) {
                $selected[$name][] = $value;
            }
        }
        ksort($selected);
        $queryValues = [];
        if ($query !== null) {
            foreach (explode('&', parse_url($request->url, PHP_URL_QUERY) ?: '') as $part) {
                [$name, $value] = array_pad(explode('=', $part, 2), 2, '');
                if (urldecode($name) === $query) {
                    $queryValues[] = urldecode($value);
                }
            }
        }

        return hash('sha256', serialize([$identity, $selected, $queryValues]));
    }

    /** Отпечаток применяется только пока фактический credential не изменён последующим hook.
     * @internal
     */
    public static function stamp(PreparedRequest $request, string $header, string $identity): PreparedRequest
    {
        $values = [];
        foreach ($request->headers as $name => $value) {
            if (strcasecmp($name, $header) === 0) {
                $values[] = $value;
            }
        }
        return $request->with(meta: [...$request->meta, 'cacheCredential' => [
            'header' => strtolower($header), 'digest' => hash('sha256', serialize($values)), 'identity' => $identity,
        ]]);
    }

    /** @internal @return array<string, string> */
    public static function headersForCache(PreparedRequest $request): array
    {
        $stamp = $request->meta['cacheCredential'] ?? null;
        if (!is_array($stamp) || !is_string($stamp['header'] ?? null) || !is_string($stamp['digest'] ?? null) || !is_string($stamp['identity'] ?? null)) {
            return $request->headers;
        }
        $values = [];
        foreach ($request->headers as $name => $value) {
            if (strtolower($name) === $stamp['header']) {
                $values[] = $value;
            }
        }
        if (!hash_equals($stamp['digest'], hash('sha256', serialize($values)))) {
            return $request->headers;
        }
        $headers = $request->headers;
        foreach ($headers as $name => $value) {
            if (strtolower($name) === $stamp['header']) {
                $headers[$name] = 'apisutra-identity:' . $stamp['identity'];
            }
        }
        return $headers;
    }

    /** @internal @return array{?string, ?string, array<string, list<string>>, list<string>} */
    public static function credentialFields(PreparedRequest $prepared): array
    {
        $headers = [];
        foreach (self::headersForCache($prepared) as $name => $value) {
            $headers[strtolower($name)][] = $value;
        }
        ksort($headers);
        $credentials = array_intersect_key($headers, array_flip([
            'authorization', 'proxy-authorization', 'cookie', 'x-api-key', 'api-key', 'x-auth-token',
        ]));
        $url = parse_url($prepared->url);
        $queryCredentials = [];
        foreach (explode('&', $url['query'] ?? '') as $part) {
            $name = strtolower(urldecode(explode('=', $part, 2)[0]));
            if (in_array($name, ['access_token', 'api_key', 'apikey', 'token', 'key', 'password', 'client_secret', 'signature'], true)) {
                $queryCredentials[] = $part;
            }
        }
        return [$url['user'] ?? null, $url['pass'] ?? null, $credentials, $queryCredentials];
    }
}
