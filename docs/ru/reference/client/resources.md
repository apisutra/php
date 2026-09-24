<!-- languages --> <a href="../../../en/reference/client/resources.md">English</a> · <a href="resources.md">Русский</a> <!-- /languages -->
# Ресурсы SDK <a id="section-1"></a>

Resources — это слой навигации между клиентом и запросами. Он помогает собрать
иерархию API в удобный fluent‑интерфейс и гарантирует, что все запросы получают
клиента автоматически.

## Когда использовать <a id="section-2"></a>
- если API состоит из логических разделов (users, orders, billing)
- если нужна явная группировка запросов по смыслу, а не по файлам
- если хочется чистого и читаемого API: `client->billing()->orders()->get($id)`

Если API небольшой (1–2 ресурса) и нет сложной навигации,
можно обойтись без ресурсов и отправлять запросы напрямую.

## Базовая идея <a id="section-3"></a>
- **Resource** хранит ссылку на клиента
- **resource()** создаёт вложенный ресурс и передаёт ему клиента
- **request()** создаёт запрос и сразу привязывает клиента

## Пример структуры <a id="section-4"></a>
```php
use ApiSutra\Attributes\Http\Get;
use ApiSutra\Core\AbstractRequest;
use ApiSutra\Core\AbstractResource;

final class BillingResource extends AbstractResource
{
    public function orders(): OrdersResource
    {
        return $this->resource(OrdersResource::class);
    }
}

final class OrdersResource extends AbstractResource
{
    public function get(string $id): GetOrder
    {
        return $this->request(GetOrder::class, $id);
    }
}

#[Get('/orders/{id}')]
final class GetOrder extends AbstractRequest
{
    public function __construct(
        public string $id,
    ) {}
}
```

## Пример с вложенным ресурсом и контекстом <a id="section-5"></a>
Иногда нужно передать контекст (например, `accountId`) в цепочку ресурсов.
Это делается через конструктор ресурса и `resource()`:

```php
use ApiSutra\Contracts\Interfaces\Core\ClientInterface;

final class AccountsResource extends AbstractResource
{
    public function account(string $accountId): AccountResource
    {
        return $this->resource(AccountResource::class, $accountId);
    }
}

final class AccountResource extends AbstractResource
{
    public function __construct(
        ClientInterface $client,
        private readonly string $accountId,
    ) {
        parent::__construct($client);
    }

    public function orders(): OrdersResource
    {
        return $this->resource(OrdersResource::class, $this->accountId);
    }
}

final class OrdersResource extends AbstractResource
{
    public function list(): ListOrders
    {
        return $this->request(ListOrders::class, $this->accountId);
    }
}
```

Зачем это нужно:
- можно строить цепочки `client->accounts()->account($id)->orders()->list()`
- контекст хранится в ресурсе и не дублируется в каждом запросе

## Что важно помнить <a id="section-6"></a>
- `request()` **обязательно** привязывает клиента — запрос готов к `send()`.
- `resource()` позволяет строить цепочки любой глубины.
- Внутри ресурсов можно передавать аргументы конструкторам (например, `accountId`).

Рекомендация: не делайте цепочки слишком глубоко, если это ухудшает читаемость.

## Где это используется <a id="section-7"></a>
- В клиенте обычно есть методы‑точки входа: `billing()`, `users()`, `files()`.
- В ресурсах находятся методы, которые возвращают **либо** следующий ресурс,
  **либо** запрос.
