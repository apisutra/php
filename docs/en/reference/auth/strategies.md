<!-- languages --> <a href="strategies.md">English</a> · <a href="../../../ru/reference/auth/strategies.md">Русский</a> <!-- /languages -->
# Choosing authentication <a id="section-1"></a>

Authentication settings in `ClientConfig`.

`ClientConfig::auth` sets the default strategy, `authScopes` defines named strategies,
and `authPolicy` checks applicability. By default, `auth`/policy are unset and scopes
are empty. Priorities and runtime resets are described below.

## Basic authenticator <a id="section-2"></a>

With `ApiKeyAuthenticator(header: null, query: 'api_key')`, the key is appended after
the query from the base URL, endpoint, and fields. Authenticating a prepared request
again replaces only the pair previously added by the SDK; original parameters with
the same name are preserved. The fragment is removed before authentication.
See [serialization](../serialization/uri-query.md#section-5) for URI rules.

```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Auth\AuthenticatorInterface;
use ApiSutra\Contracts\Interfaces\Core\RequestInterface;
use ApiSutra\Contracts\Interfaces\DataTransfer\ResponseDtoInterface;
use ApiSutra\VO\Http\PreparedRequest;

final class ExampleAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private string $token,
    ) {}

    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        return $request->withHeader('Authorization', 'Bearer ' . $this->token);
    }

    public function shouldRefresh(): bool
    {
        return false;
    }

    public function getRefreshRequest(): ?RequestInterface
    {
        return null;
    }

    public function processTokenResponse(ResponseDtoInterface $response): void
    {
    }
}

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    auth: new ExampleAuthenticator('token'),
);
```

## API key in the query <a id="section-3"></a>
To send a token as a query parameter, use the built-in `ApiKeyAuthenticator`, set
`query`, and disable `header`:

```php
use ApiSutra\Auth\ApiKeyAuthenticator;

$config = $config->with(
    auth: new ApiKeyAuthenticator('token', header: null, query: 'api_key'),
);
```

## API key in a header <a id="section-4"></a>
By default, `ApiKeyAuthenticator` puts the key in `X-Api-Key`:
```php
use ApiSutra\Auth\ApiKeyAuthenticator;

$config = $config->with(
    auth: new ApiKeyAuthenticator('token'),
);
```

## Bearer token <a id="section-5"></a>
```php
use ApiSutra\Auth\BearerAuthenticator;

$config = $config->with(
    auth: new BearerAuthenticator('token'),
);
```

## Basic Auth <a id="section-6"></a>
```php
use ApiSutra\Auth\BasicAuthenticator;

$config = $config->with(
    auth: new BasicAuthenticator('user', 'pass'),
);
```

## HMAC signature <a id="section-7"></a>
```php
use ApiSutra\Auth\HmacAuthenticator;

$config = $config->with(
    auth: new HmacAuthenticator('api-key', 'secret'),
);
```

## AuthorizationSchemeAuthenticator <a id="section-8"></a>
For schemes such as `Authorization: Scheme key="value", ts="..."`.

```php
use ApiSutra\Auth\Authorization\QueryLikeFormatter;
use ApiSutra\Auth\AuthorizationSchemeAuthenticator;
use ApiSutra\Contracts\Interfaces\Auth\AuthorizationParamsProviderInterface;
use ApiSutra\VO\Http\PreparedRequest;

final readonly class SignatureParamsProvider implements AuthorizationParamsProviderInterface
{
    public function __construct(
        private string $apiKey,
        private string $secret,
    ) {}

    public function resolve(PreparedRequest $request): array
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $request->url . $timestamp, $this->secret);

        return [
            'key' => $this->apiKey,
            'ts' => $timestamp,
            'sign' => $signature,
        ];
    }
}

$config = $config->with(
    auth: new AuthorizationSchemeAuthenticator(
        scheme: 'Signature',
        provider: new SignatureParamsProvider('api-key', 'secret'),
    ),
);
```

For an unquoted `key=value&key2=value2` format, use `QueryLikeFormatter`:
```php
$config = $config->with(
    auth: new AuthorizationSchemeAuthenticator(
        scheme: 'ReestroAuth',
        params: [
            'apiKey' => 'key',
            'portal.orgid' => 'org',
        ],
        formatter: new QueryLikeFormatter(),
    ),
);
```

## Authorization: scheme and parameters <a id="section-9"></a>
For a custom `Authorization` format (scheme and parameters),
`AuthorizationSchemeAuthenticator` is sufficient in most cases.
For special cases, implement `AuthenticatorInterface` yourself:
```php
final class CustomSchemeAuthenticator implements AuthenticatorInterface
{
    public function __construct(
        private string $key,
        private string $secret,
    ) {}

    public function authenticate(PreparedRequest $request): PreparedRequest
    {
        $timestamp = time();
        $signature = hash_hmac('sha256', $this->key . $timestamp, $this->secret);
        $value = sprintf(
            'Custom key="%s", ts="%s", sign="%s"',
            $this->key,
            $timestamp,
            $signature,
        );

        return $request->withHeader('Authorization', $value);
    }

    public function shouldRefresh(): bool
    {
        return false;
    }

    public function getRefreshRequest(): ?RequestInterface
    {
        return null;
    }

    public function processTokenResponse(ResponseDtoInterface $response): void
    {
    }
}
```

## Multiple scopes <a id="section-10"></a>
```php
use ApiSutra\Attributes\Request\AuthScope;
use ApiSutra\Core\AbstractRequest;
use App\Provider\Auth\ProviderScope;

$config = $config->with(authScopes: [
    'public' => new ApiKeyAuthenticator('public'),
    'private' => new ApiKeyAuthenticator('private'),
]);

#[AuthScope(ProviderScope::Private)] // recommended
final class GetPrivateData extends AbstractRequest {}

#[AuthScope('private')] // Explicit scope
final class ScopedPrivateData extends AbstractRequest {}
```

By default, when only `auth` is set, it applies to all requests
(except `#[NoAuth]`/`withoutAuth()`). If you use only `authScopes` without `auth`,
you must select a scope explicitly on the request (`#[AuthScope]`) or through a
runtime override; otherwise authentication is not applied.

## Request-level controls <a id="section-11"></a>
- `#[AuthScope]` selects a scope.
- `#[NoAuth]` disables authentication for the request.
- Runtime: `withAuth()`, `withoutAuth()`, `withAuthScope()`, `forceAuth()`, `forceAuthScope()`.

```php
use ApiSutra\Attributes\Behavior\NoAuth;

#[NoAuth]
final class PublicPing extends AbstractRequest {}

$request = (new PublicPing())->withoutAuth();
$request = (new PublicPing())->forceAuth(); // use only for exceptions
```

`withAuthScope()` does not override `#[NoAuth]`; it replaces a previous runtime
`withoutAuth()` with the new runtime selection. `forceAuthScope()` overrides the
prohibition and is needed only for rare exceptions.

## Authentication selection priorities <a id="section-12"></a>
Decision order:
1) `forceAuth/forceAuthScope`
2) `#[NoAuth]` / `withoutAuth()`
3) Runtime scope override (`withAuthScope`)
4) `#[AuthScope]`
5) `withAuth()`
6) `AuthPolicyInterface`
7) Default `auth` from `ClientConfig`

## Resetting the runtime scope <a id="section-13"></a>

`withAuth()`, `withoutAuth()`, and `forceAuth()` clear the previous runtime scope.
For example, `withAuthScope('secondary')->withoutAuth()->withAuth()` returns to
default credentials if the class has no `#[AuthScope]`. If the attribute is present,
its scope takes effect again after the reset. The original execution copy with
`secondary` remains unchanged.

`withOptions()` replaces the entire options snapshot: a cleared execution scope is
not restored from the original request's runtime scope. `NoAuth`, `AuthPolicy`, and
permission through `forceAuth` still apply. In a chain of runtime methods, the last
setting replaces the previous one; for example, `forceAuth()->withoutAuth()` disables authentication.

To retain a selected scope, use explicit `withAuthScope()` or `forceAuthScope()`.
Scope resets do not change optional TTL or connect timeout settings.

## Access policy <a id="section-14"></a>
```php
use ApiSutra\Contracts\Interfaces\Auth\AuthPolicyInterface;

final class OnlyWritePolicy implements AuthPolicyInterface
{
    public function allowedRequests(): array
    {
        return [
            CreateUser::class,
            UpdateUser::class,
        ];
    }
}

$config = $config->with(authPolicy: new OnlyWritePolicy());
```

Without `AuthPolicy`, default `auth` applies to all requests. Use `AuthPolicy` to
restrict authentication to a subset of requests or separate system and user calls.

## Built-in authenticators <a id="section-15"></a>
- `BearerAuthenticator` — `Authorization: Bearer <token>`
- `ApiKeyAuthenticator` — an API key in a header or query
- `BasicAuthenticator` — `Authorization: Basic base64(user:pass)`
- `AuthorizationSchemeAuthenticator` — `Authorization: Scheme key="value", ...`
- `TokenAuthenticator` — a token with a refresh request and caching
- `HmacAuthenticator` — a request signature (`X-Api-Key`/X-Timestamp/X-Signature)
