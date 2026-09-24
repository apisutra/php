<!-- languages --> <a href="../../../en/reference/client/discovery.md">English</a> · <a href="discovery.md">Русский</a> <!-- /languages -->
# Поиск клиента для запроса <a id="section-1"></a>

Этот механизм позволяет автоматически находить «владельца» запроса
по его namespace и не передавать клиента вручную.

## Компоненты <a id="section-2"></a>
- **ClientRegistry** — хранит соответствия `namespace → Client`.
- **ClientResolver** — ищет клиента по классу запроса.
- **ClientDiscoveryService** — auto‑discovery запросов и регистрация в реестре.

## Как работает resolve <a id="section-3"></a>
1) Из класса запроса берётся namespace.
2) В `ClientRegistry` выбирается **самый длинный совпадающий** namespace.
3) Если совпадений нет — `ConfigurationException`.

Это позволяет безопасно работать с вложенными структурами.

## Ручная регистрация (простая) <a id="section-4"></a>
```php
use ApiSutra\Resolver\ClientRegistry;

$registry = new ClientRegistry();
$registry->register($client, 'Vendor\\Package\\Requests');
```

## Auto‑discovery <a id="section-5"></a>
Auto‑discovery сканирует namespace клиента и находит классы запросов.
Он использует classmap Composer (если доступен) и PSR‑4 scan как fallback.

```php
use ApiSutra\Resolver\ClientDiscoveryCache;
use ApiSutra\Resolver\ClientDiscoveryService;
use ApiSutra\Resolver\ClassMapProvider;
use ApiSutra\Resolver\DiscoveryOptions;
use ApiSutra\Resolver\RequestNamespaceDetector;
use ApiSutra\Resolver\RequestScanner;

$registry = new ClientRegistry();
$detector = new RequestNamespaceDetector(new RequestScanner(new ClassMapProvider()));
$cache = new ClientDiscoveryCache($psr16Cache);
$service = new ClientDiscoveryService($registry, $detector, $cache);

$service->registerAuto($client, DiscoveryOptions::auto());
```

### Что именно сканируется <a id="section-6"></a>
1) **Root namespace** клиента (первые два сегмента: `Vendor\\Package`).
2) Если ничего не найдено — fallback на:
   - `Vendor\\Package\\Requests`
   - `Vendor\\Package\\Resources`

### Кеш discovery <a id="section-7"></a>
`DiscoveryOptions` управляет кешем:
- `Auto` — включён в `Production/Staging`, выключен в `Local/Testing`
- `ForceOn` — всегда включён
- `ForceOff` — всегда выключен

Дополнительно можно задать:
- `cacheTtl` — TTL для кеша
- `cacheKeyVersion` — ручная версия ключа для инвалидации

По умолчанию `cacheTtl = null` (используется TTL стора),
`cacheKeyVersion = null` (не участвует в ключе).
`cacheTtl` имеет смысл задавать при дорогом сканировании или большом количестве клиентов.
`cacheKeyVersion` используйте, когда изменили структуру namespace и хотите
принудительно инвалидировать кеш после деплоя.

Ключ кеша включает:
`ClientClass + cacheKeyVersion + checksum Composer`.

## Мультисервисные клиенты <a id="section-8"></a>
Если у провайдера несколько сервис‑клиентов, зарегистрируйте namespace‑ы
каждого сервиса через `ServiceRegistrar`.
По умолчанию используется `RequestNamespaceDetector`, а при необходимости
можно задать список вручную через `RequestNamespaceProviderInterface`.

```php
use ApiSutra\Resolver\ServiceRegistrar;

$registrar->register($mega->services());
```

В Laravel `SdkServiceProvider` делает это автоматически при резолве
`MultiServiceClientInterface`.

## Auto‑resolve в запросах <a id="section-9"></a>
`AbstractRequest` пытается получить `ClientResolverInterface` из контейнера
через `ContainerProviderRegistry`. Если резолвер найден — запрос сам
находит клиента по `ClientRegistry`.

Если контейнера нет или резолвер не зарегистрирован, нужно:
- либо отправлять через клиент: `$client->send($request)`
- либо вручную привязать клиента: `$request->setClient($client)`

## Рекомендации <a id="section-10"></a>
- В production включайте classmap Composer — сканирование будет быстрым.
- Для больших проектов используйте `cacheKeyVersion`, чтобы вручную
  инвалидировать кеш при смене структуры namespace.
