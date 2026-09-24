<!-- languages --> <a href="../../../en/reference/auth/strategies.md">English</a> · <a href="strategies.md">Русский</a> <!-- /languages -->
# Выбор аутентификации <a id="section-1"></a>

Настройки авторизации в `ClientConfig`.

`ClientConfig::auth` задаёт стратегию по умолчанию, `authScopes` — именованные
стратегии, `authPolicy` — проверку применимости. По умолчанию auth/policy не заданы,
scopes пуст. Приоритеты и runtime-сброс описаны ниже.

## Базовый authenticator <a id="section-2"></a>

Для `ApiKeyAuthenticator` с `header: null, query: 'api_key'` ключ добавляется
после query base URL, endpoint и полей. Повторная авторизация подготовленного запроса
заменяет только ранее добавленную SDK пару; исходные одноимённые параметры сохраняются.
Fragment до авторизации удаляется. Правила URI — в [сериализации](../serialization/uri-query.md#section-5).

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

## API key в query <a id="section-3"></a>
Если токен должен передаваться в query‑параметре, используйте встроенный
`ApiKeyAuthenticator` и укажите `query` (а `header` отключите):

```php
use ApiSutra\Auth\ApiKeyAuthenticator;

$config = $config->with(
    auth: new ApiKeyAuthenticator('token', header: null, query: 'api_key'),
);
```

## API key в header <a id="section-4"></a>
По умолчанию `ApiKeyAuthenticator` кладёт ключ в `X-Api-Key`:
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

## HMAC‑подпись <a id="section-7"></a>
```php
use ApiSutra\Auth\HmacAuthenticator;

$config = $config->with(
    auth: new HmacAuthenticator('api-key', 'secret'),
);
```

## AuthorizationSchemeAuthenticator <a id="section-8"></a>
Подходит для схем вида `Authorization: Scheme key="value", ts="..."`.

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

Если нужен формат `key=value&key2=value2` без кавычек, используйте `QueryLikeFormatter`:
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

## Authorization: scheme + параметры <a id="section-9"></a>
Если нужен нестандартный формат `Authorization` (схема + параметры),
в большинстве случаев достаточно `AuthorizationSchemeAuthenticator`.
Для особых случаев можно реализовать `AuthenticatorInterface` вручную:
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

## Несколько scope <a id="section-10"></a>
```php
use ApiSutra\Attributes\Request\AuthScope;
use ApiSutra\Core\AbstractRequest;
use App\Provider\Auth\ProviderScope;

$config = $config->with(authScopes: [
    'public' => new ApiKeyAuthenticator('public'),
    'private' => new ApiKeyAuthenticator('private'),
]);

#[AuthScope(ProviderScope::Private)] // рекомендуемый вариант
final class GetPrivateData extends AbstractRequest {}

#[AuthScope('private')] // Явный scope
final class ScopedPrivateData extends AbstractRequest {}
```

По умолчанию, если задан только `auth`, он применяется ко всем запросам
(кроме `#[NoAuth]`/`withoutAuth()`).
Если вы используете только `authScopes` и не задали `auth`, то scope нужно
выбирать явно на запросе (`#[AuthScope]`) или через runtime‑override,
иначе авторизация не будет применена.

## Управление на уровне запроса <a id="section-11"></a>
- `#[AuthScope]` — выбрать scope.
- `#[NoAuth]` — отключить auth на запросе.
- Runtime: `withAuth()`, `withoutAuth()`, `withAuthScope()`, `forceAuth()`, `forceAuthScope()`.

```php
use ApiSutra\Attributes\Behavior\NoAuth;

#[NoAuth]
final class PublicPing extends AbstractRequest {}

$request = (new PublicPing())->withoutAuth();
$request = (new PublicPing())->forceAuth(); // использовать только для исключений
```

`withAuthScope()` не пробивает `#[NoAuth]`; предыдущий runtime `withoutAuth()`
заменяется новым runtime-выбором.
`forceAuthScope()` пробивает запрет и нужен только для редких исключений.

## Приоритеты выбора auth <a id="section-12"></a>
Порядок принятия решения:
1) `forceAuth/forceAuthScope`
2) `#[NoAuth]` / `withoutAuth()`
3) runtime‑override scope (`withAuthScope`)
4) `#[AuthScope]`
5) `withAuth()`
6) `AuthPolicyInterface`
7) дефолтный `auth` из `ClientConfig`

## Сброс runtime scope <a id="section-13"></a>

`withAuth()`, `withoutAuth()` и `forceAuth()` очищают предыдущий runtime scope.
Например, `withAuthScope('secondary')->withoutAuth()->withAuth()` возвращает
выбор к default credentials, если у класса нет `#[AuthScope]`. При наличии
атрибута после сброса снова действует его scope. Исходная execution-копия
с `secondary` не меняется.

`withOptions()` заменяет полный снимок опций: очищенный scope execution не
восстанавливается из runtime scope исходного request. Правила `NoAuth`,
`AuthPolicy` и разрешение через `forceAuth` сохраняются. В цепочке runtime-методов
последняя настройка заменяет предыдущую; например, `forceAuth()->withoutAuth()`
отключает авторизацию.

Для сохранения выбранного scope используйте явные `withAuthScope()` или `forceAuthScope()`.
Сброс scope не меняет необязательные настройки TTL или connect timeout.

## Политика доступа <a id="section-14"></a>
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

Без `AuthPolicy` дефолтный `auth` применяется ко всем запросам.
`AuthPolicy` нужен, если хотите ограничить auth только частью запросов
или разделить системные/пользовательские вызовы.

## Встроенные аутентификаторы <a id="section-15"></a>
- `BearerAuthenticator` — `Authorization: Bearer <token>`
- `ApiKeyAuthenticator` — API‑ключ в header или query
- `BasicAuthenticator` — `Authorization: Basic base64(user:pass)`
- `AuthorizationSchemeAuthenticator` — `Authorization: Scheme key="value", ...`
- `TokenAuthenticator` — токен с refresh‑запросом и кешированием
- `HmacAuthenticator` — подпись запроса (X‑Api‑Key/X‑Timestamp/X‑Signature)
