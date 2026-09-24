<!-- languages --> <a href="../../../en/guides/integration/standalone.md">English</a> · <a href="standalone.md">Русский</a> <!-- /languages -->
# Подключить без Laravel <a id="section-1"></a>

Конструктор клиента принимает `ClientConfig` и `TransportInterface` явно. Для
ресурсного вызова контейнер не нужен: ресурс привязывает создаваемый запрос к клиенту.

## Проверить сборку без сети <a id="section-2"></a>

[Учебный SDK](../../examples/sdk.md) передаёт `MockTransport` и
[конфигурацию](../../../example/sdk/src/Config/ClientConfigFactory.php) в `DemoClient`.
Сначала запустите его, затем замените параметры на данные своего API.

## Настоящий HTTP <a id="section-3"></a>

Для стандартного транспорта нужны Guzzle HTTP client и расширение PHP `ext-curl`:

```bash
composer require guzzlehttp/guzzle:^7
```

Фрагмент подключения после Composer autoload и автозагрузки вашего SDK:

```php
use ApiSutra\Transport\HttpTransport;
use Example\Records\Config\ClientConfigFactory;
use Example\Records\DemoClient;

$client = new DemoClient(
    ClientConfigFactory::create('https://api.example.test'),
    HttpTransport::createDefault(),
);
```

Замените пример URL настоящим адресом. Само создание транспорта HTTP не выполняет.
Если нужен другой PSR-18 клиент, передайте его вместе с PSR-17 фабриками в
`HttpTransport`. [Контракт транспорта](../../reference/execution/transport.md)
описывает поддержку timeout, redirects и потоков.

## Необязательные интеграции <a id="section-4"></a>

Внешний кеш использует PSR-16. Без него клиент сохраняет только доступное локальное
состояние механизмов; [кеш ответов](../../reference/execution/cache.md) подключается явно.
Для `#[Validate]` нужна фабрика Illuminate Validation, которую можно передать через
`Validator::useFactory()` без приложения Laravel. [Настройка и приоритеты](../../reference/client/validation.md).

Собственный контейнер подключается через [ContainerProviderInterface](../../reference/client/construction.md).
Это отдельная возможность, а не условие для обычного запроса.
