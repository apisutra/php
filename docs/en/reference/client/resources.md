<!-- languages --> <a href="resources.md">English</a> · <a href="../../../ru/reference/client/resources.md">Русский</a> <!-- /languages -->
# SDK resources <a id="section-1"></a>

Resources provide navigation between clients and requests. They turn API hierarchy
into a convenient fluent interface and ensure all requests receive the client automatically.

## When to use resources <a id="section-2"></a>
- The API has logical sections such as users, orders, or billing.
- Requests need meaningful explicit grouping beyond their file locations.
- You want a readable API such as `client->billing()->orders()->get($id)`.

For a small API (1–2 resources) without complex navigation, you can send requests directly
without a resource layer.

## Basic idea <a id="section-3"></a>
- **Resource** holds a client reference.
- **`resource()`** creates a nested resource and passes it the client.
- **`request()`** creates a request and immediately binds the client.

## Structure example <a id="section-4"></a>
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

## Nested resource with context <a id="section-5"></a>
To pass context such as `accountId` through a resource chain, use the resource
constructor and `resource()`:

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

This allows:
- Chains such as `client->accounts()->account($id)->orders()->list()`.
- Context stored in a resource rather than duplicated in each request.

## Key rules <a id="section-6"></a>
- `request()` **always** binds the client; the request is ready for `send()`.
- `resource()` supports chains of any depth.
- Resources can pass constructor arguments, such as `accountId`.

Avoid excessively deep chains when they harm readability.

## Where resources appear <a id="section-7"></a>
- Clients normally expose entry methods such as `billing()`, `users()`, and `files()`.
- Resource methods return **either** another resource **or** a request.
