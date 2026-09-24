<!-- languages --> <a href="../../en/glossary/client.md">English</a> · <a href="client.md">Русский</a> <!-- /languages -->
# Клиент <a id="section-1"></a>

| Термин | Значение | Подробнее |
| --- | --- | --- |
| <a id="abstractclient"></a> AbstractClient | Базовый класс для API-клиентов. | [Контракт](../reference/client/configuration.md) |
| <a id="clientinterface"></a> ClientInterface | Интерфейс для клиентов SDK. | [Контракт](../reference/client/configuration.md) |
| <a id="clientconfig"></a> ClientConfig | Неизменяемая конфигурация клиента; сам транспорт передаётся в конструктор отдельно. | [Контракт](../reference/client/configuration.md) |
| <a id="delay"></a> delay | Параметр ClientConfig. | [Контракт](../reference/execution/deadlines.md) |
| <a id="throwonerrors"></a> throwOnErrors | Параметр ClientConfig. | [Контракт](../reference/results/errors.md) |
| <a id="archiveconfig"></a> ArchiveConfig | VO конфигурации архивов. | [Контракт](../reference/files/archives.md) |
| <a id="retryconfig"></a> RetryConfig | VO конфигурации retry. | [Контракт](../reference/execution/retry.md) |
| <a id="cacheconfig"></a> CacheConfig | Единый блок store и параметров кеша в ClientConfig; with() копирует отдельные поля. | [Контракт](../reference/execution/cache.md) |
| <a id="cachemode"></a> CacheMode | Enum режима кеша. | [Контракт](../reference/execution/cache.md) |
| <a id="ratelimitconfig"></a> RateLimitConfig | VO конфигурации rate‑limit. | [Контракт](../reference/execution/rate-limit.md) |
| <a id="backoffstrategy"></a> BackoffStrategy | Способ увеличения паузы между повторами: постоянный, линейный или экспоненциальный. | [Контракт](../reference/execution/retry.md) |
| <a id="poolconfig"></a> PoolConfig | VO конфигурации пула запросов (pool). | [Контракт](../reference/execution/batch-pool.md) |
| <a id="poolexecutor"></a> PoolExecutor | Выполнение потока запросов с ограничением числа активных задач; фактическая конкурентность зависит от транспорта. | [Контракт](../reference/execution/batch-pool.md) |
| <a id="concurrencyresolverinterface"></a> ConcurrencyResolverInterface | Интерфейс определения стартовой concurrency pool; отдельно API допускает callable. | [Контракт](../reference/execution/batch-pool.md) |
| <a id="namingstrategy"></a> NamingStrategy | Правило преобразования имён полей: сохранить имя или использовать snake_case. | [Контракт](../reference/dto/profiles.md) |
| <a id="requestpartsenumoutput"></a> requestPartsEnumOutput | Параметр ClientConfig. | [Контракт](../reference/serialization/request-parts.md) |
| <a id="requestpartsstrictenums"></a> requestPartsStrictEnums | Параметр ClientConfig. | [Контракт](../reference/serialization/request-parts.md) |
| <a id="wirebodyserializationpolicy"></a> wireBodySerializationPolicy | Transport-level body serialization policy в `ClientConfig`. | [Контракт](../reference/serialization/dto-output.md) |
| <a id="enumoutput"></a> EnumOutput | Представление enum при сериализации: значение, имя, объект или строка с названием и значением. | [Контракт](../reference/serialization/dto-output.md) |
| <a id="requestdatetime"></a> requestDateTime | Request-level date-time serialization policy в `ClientConfig`. | [Контракт](../reference/serialization/request-parts.md) |
| <a id="dtohydrate"></a> DtoHydrate | Атрибут частичной настройки входной модели поверх профиля. | [Контракт](../reference/dto/profiles.md) |
| <a id="datetimeinvalidbehavior"></a> DateTimeInvalidBehavior | Реакция на невалидную дату при гидратации: исключение или null с последующей проверкой типа поля. | [Контракт](../reference/dto/profiles.md) |
| <a id="environment"></a> Environment | Окружение клиента; влияет, в частности, на режим кеша метаданных и discovery. | [Контракт](../reference/client/configuration.md) |
| <a id="errorcode"></a> ErrorCode | Системный код ошибки ApiSutra; HTTP-статусы сопоставляются ему отдельно от бизнес-кода провайдера. | [Контракт](../reference/results/errors.md) |
| <a id="client-auto-discovery"></a> Auto-discovery клиентов | Механизм автоматического поиска и регистрации namespace запросов клиента. | [Контракт](../reference/client/discovery.md) |
| <a id="clientregistry"></a> ClientRegistry | Сопоставляет namespace запросов клиентам; выбирает наиболее длинное совпадение и проверяет владельца запроса. | [Контракт](../reference/client/discovery.md) |
| <a id="clientresolverinterface"></a> ClientResolverInterface | Контракт выбора клиента для запроса и проверки его принадлежности этому клиенту. | [Контракт](../reference/client/discovery.md) |
| <a id="clientresolver"></a> ClientResolver | Разрешает клиента через registry, учитывая обёртки исполнения запроса. | [Контракт](../reference/client/discovery.md) |
| <a id="discoveryoptions"></a> DiscoveryOptions | Настройки поиска запросов клиента и кеширования его результатов. | [Контракт](../reference/client/discovery.md) |
| <a id="discoverycachemode"></a> DiscoveryCacheMode | Режим кеширования discovery: выбор по окружению, принудительное включение или отключение. | [Контракт](../reference/client/discovery.md) |
| <a id="clientdiscoveryservice"></a> ClientDiscoveryService | Находит namespace запросов клиента и регистрирует их в ClientRegistry. | [Контракт](../reference/client/discovery.md) |
| <a id="clientdiscoverycache"></a> ClientDiscoveryCache | Хранит найденные namespace в PSR-16 кеше либо в памяти процесса. | [Контракт](../reference/client/discovery.md) |

[Все термины](README.md).
