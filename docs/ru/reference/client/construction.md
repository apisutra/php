<!-- languages --> <a href="../../../en/reference/client/construction.md">English</a> · <a href="construction.md">Русский</a> <!-- /languages -->
# Создание клиента и зависимости <a id="section-1"></a>

Настройка интеграции с контейнером через `ContainerProviderInterface`.

## Поведение по умолчанию <a id="section-2"></a>
Ядро не определяет фреймворк. Вне активного исполнения приоритет: override вызова/клиента →
явный `ContainerProviderRegistry::set()` → default интеграции (`setDefault()` или
`setDefaultResolver()`) → `NullContainerProvider`.
Исполнение сохраняет выбранный provider во вложенных вызовах и между приостановками Fiber;
явный override вызова/клиента сохраняет приоритет. Новые регистрации влияют на следующие
исполнения. `reset()` очищает explicit/default и default resolver, сохраняя активные области
до их выхода через `finally`, включая приостановленные Fiber.
Null в ClientConfig использует реестр; явный NullContainerProvider отключает контейнер.

Во время канонического выполнения хуки запроса получают исполняющий клиент через
`getClient()` или protected-свойство `$client`. Привязка изолирована по Fiber и
вложенным вызовам; после выполнения восстанавливается постоянная привязка. Явный
`setClient()` по-прежнему проверяет владение namespace.

## Если Laravel не используется <a id="section-3"></a>
Ничего делать не нужно, если вы:
- явно передаёте клиент при отправке (`$client->send($request)`) или через `$request->setClient($client)`
- не используете auto‑resolve клиента и валидатор DTO на уровне контейнера

Что будет без контейнера:
- `ClientResolverInterface` не будет найден автоматически
- при наличии `#[Validate]` без настроенной фабрики будет `configuration_error`;
  запросам без этих правил фабрика не нужна
- `ClientDiscoveryService` возьмёт basePath из `getcwd()`
- `debug/environment` задаются вручную в `ClientConfig`

Если контейнер всё же нужен — подключите свой провайдер.
Provider позволяет настроить auto-resolve клиента и получение
`basePath`/`environment` без Laravel. Для валидации достаточно фабрики.

Для валидации без контейнера приложения можно настроить фабрику через
`Validator::useFactory()`; [пример standalone и приоритеты](validation.md#section-5).
Приложение Laravel для этого не требуется, компоненты Illuminate Validation нужны
только при использовании соответствующих правил.

## Переопределение через ClientConfig <a id="section-4"></a>
```php
use ApiSutra\Config\ClientConfig;
use ApiSutra\Contracts\Interfaces\Container\ContainerProviderInterface;

final class ExampleContainerProvider implements ContainerProviderInterface
{
    public function bound(string $id): bool { return false; }
    public function make(string $id): ?object { return null; }
    public function basePath(): ?string { return null; }
    public function environment(): ?string { return null; }
    public function isDebug(): ?bool { return null; }
    public function validatorFactory(): ?object { return null; }
}

$provider = new ExampleContainerProvider();

$config = new ClientConfig(
    baseUrl: 'https://api.example',
    containerProvider: $provider,
);
```

Явный `containerProvider` клиента определяет фабрику проверки его запросов.
Null от `validatorFactory()` не включает fallback к глобальной фабрике: при наличии
правил это ошибка конфигурации. Ручная проверка уже привязанного запроса использует
тот же источник. Для standalone DTO provider можно передать явно в
`Validator::check($dto, provider: $provider)`.

## Глобальная настройка (bootstrap) <a id="section-5"></a>
```php
use ApiSutra\Support\ContainerProviderRegistry;

ContainerProviderRegistry::set($provider);
```

## Зачем это нужно <a id="section-6"></a>
Провайдер влияет на:
- `ClientResolver` для auto‑resolve клиента в запросе
- `Validator` для валидации запросов и DTO по [правилам выбора контекста](validation.md#section-6)
- `ClientDiscoveryService` для basePath

Также провайдер можно использовать для автоматического получения транспорта
в high-level фабриках клиента через `TransportResolver::resolve(...)`.
Это удобно для `Client::make(...)`, но не меняет low-level контракт конструктора,
где `transport` передаётся явно.

С установленным `apisutra/laravel` provider подключается package discovery без обязательной публикации конфига.
Обычный DI сохраняет заданные значения SDK-запроса; перенос входящих HTTP-данных
выполняется явной RequestFactory. Пользовательские bindings имеют приоритет.
[Подключение и тестирование](https://github.com/apisutra/laravel/blob/master/docs/ru/guides/integration/laravel.md).

`DefaultTransportFactory::create(ContainerProviderInterface $app)` собирает HttpTransport
из PSR-18/PSR-17 bindings контейнера с Guzzle fallback. Она не регистрирует
транспорт и не меняет выбор явного/контейнерного транспорта в TransportResolver.
Laravel использует её для ленивого default binding; standalone-код может вызвать
её явно. Неверные bindings дают ConfigurationException до fallback.

## Контракт мегаклиента <a id="section-7"></a>
`MultiServiceClientInterface` требует вернуть список сервис‑клиентов.
```php
use ApiSutra\Contracts\Interfaces\Core\MultiServiceClientInterface;

final readonly class MegaClient implements MultiServiceClientInterface
{
    public function __construct(
        private RealtyClient $realty,
        private TaxClient $tax,
    ) {}

    public function services(): array
    {
        return [$this->realty, $this->tax];
    }
}
```
