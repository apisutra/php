<!-- languages --> <a href="../../../en/reference/client/configuration.md">English</a> · <a href="configuration.md">Русский</a> <!-- /languages -->
# Параметры ClientConfig <a id="section-1"></a>

[Обзор на одном примере](../../guides/client/showcase.md) показывает, как настройки
меняют выполнение запросов. Ниже находится полный каталог параметров.

`ApiSutra\Config\ClientConfig` — неизменяемая конфигурация одного клиента.
Обязателен `baseUrl`; транспорт передаётся отдельно в конструктор клиента.
[Полная сборка](construction.md) и [исполняемый пример](../../../example/sdk/src/Config/ClientConfigFactory.php).

## Создать и изменить <a id="section-2"></a>

Фрагмент для приложения с Composer autoload:

```php
use ApiSutra\Config\ClientConfig;

$config = new ClientConfig(baseUrl: 'https://api.example.test');
$another = $config->with(timeout: 15, hydration: null);
```

`with()` создаёт новую конфигурацию, сохраняет поля без override и учитывает явный
`null`. Например, `with(hydration: null)` отключает набор в копии. Изменение
конфигурации не перестраивает уже созданного клиента: используйте копию для нового
экземпляра. Необязательная `ApiSutra\Laravel\ClientConfigFactory` из `apisutra/laravel` подбирает defaults приложения;
внешние правила собирайте в фабрике/provider, а не в кешируемом config-массиве.

Конструктор проверяет сочетания параметров и выбрасывает `ConfigurationException`
при неверной конфигурации. Значения `timeout`/`connectTimeout` задаются в секундах,
`delay` — в миллисекундах; остальные единицы указаны у тематических владельцев.

## Каталог параметров <a id="section-3"></a>

Таблица перечисляет все параметры конструктора. Полные defaults, приоритеты и
ограничения хранятся в соответствующем разделе, а не дублируются здесь.

| Параметр | PHP-тип | Владелец контракта |
| --- | --- | --- |
| `baseUrl` | `string` | [Сборка URI и переопределение адреса](../serialization/uri-query.md) |
| `auth` | `?AuthenticatorInterface` | [Выбор auth](../auth/strategies.md) |
| `authScopes` | `array` | [Выбор auth](../auth/strategies.md) |
| `authPolicy` | `?AuthPolicyInterface` | [Выбор auth](../auth/strategies.md) |
| `authRetryOn401` | `bool` | [Refresh и 401](../auth/tokens.md) |
| `authRetryAttempts` | `int` | [Refresh и 401](../auth/tokens.md) |
| `logger` | `?LoggerInterface` | [Логи и debug](../results/observability.md) |
| `logLevel` | `string` | [Логи и debug](../results/observability.md) |
| `cacheConfig` | `?CacheConfig` | [Единый блок кеша](../execution/cache.md): store и параметры HTTP/auth |
| `timeout` | `int` | [Лимиты времени](../execution/deadlines.md) |
| `connectTimeout` | `int` | [Лимиты времени](../execution/deadlines.md) |
| `retry` | `?RetryConfig` | [Повторные попытки](../execution/retry.md) |
| `rateLimit` | `?RateLimitConfig` | [Квоты](../execution/rate-limit.md) |
| `pool` | `?PoolConfig` | [Batch и pool](../execution/batch-pool.md) |
| `queryArrayFormat` | `QueryArrayFormat` | [Формат query](../serialization/uri-query.md) |
| `serializeNulls` | `bool` | [Сборка частей запроса](../serialization/request-parts.md) |
| `namingStrategy` | `NamingStrategy` | [Сборка частей запроса](../serialization/request-parts.md) |
| `casts` | `array` | [Исходящие casts](../serialization/casts.md) |
| `dtoSerializationProfile` | `?DtoSerializationProfileInterface` | [DTO: DX и wire](../serialization/dto-output.md) |
| `wireBodySerializationPolicy` | `?DtoSerializationPolicy` | [DTO: DX и wire](../serialization/dto-output.md) |
| `requestPartsEnumOutput` | `EnumOutput` | [Значения частей запроса](../serialization/request-parts.md) |
| `requestPartsStrictEnums` | `bool` | [Значения частей запроса](../serialization/request-parts.md) |
| `requestDateTime` | `?DateTimeSerializationPolicy` | [Даты в исходящем представлении](../serialization/dto-output.md) |
| `delay` | `int` | [Лимиты времени](../execution/deadlines.md) |
| `resultExceptions` | `?ResultExceptionConfig` | [Сообщения и собственные исключения](../results/exceptions.md) |
| `throwOnErrors` | `bool` | [Доставка ошибок](../results/errors.md) |
| `debug` | `bool` | [Логи и debug](../results/observability.md) |
| `environment` | `Environment` | [Логи и debug](../results/observability.md) |
| `idempotencyHeader` | `string` | [Повторные попытки](../execution/retry.md) |
| `extensions` | `array` | [Расширения](../extensions/extensions.md) |
| `paginationConfig` | `?PaginationConfig` | [Пагинация](../execution/pagination.md) |
| `archive` | `?ArchiveConfig` | [Архивы](../files/archives.md) |
| `paginationRule` | `?PaginationRule` | [Пагинация](../execution/pagination.md) |
| `resolvedResultFactory` | `?ResolvedResultFactoryInterface` | [Контракт](../results/handles.md#section-10), [рецепт своего результата](../../guides/recipes/custom-result.md) |
| `errorMapper` | `?ClientErrorMapperInterface` | [Маппинг ошибок](../results/errors.md) |
| `errorContextFactory` | `?ErrorContextFactoryInterface` | [Маппинг ошибок](../results/errors.md) |
| `responseFactory` | `?ClientResponseFactoryInterface` | [Представление результата](../results/handles.md) |
| `containerProvider` | `?ContainerProviderInterface` | [Контейнер и явная сборка](construction.md) |
| `requestEnrichers` | `array` | [Credentials и origin](../auth/credentials.md) |
| `credentialsConfig` | `?CredentialsEnrichmentConfig` | [Credentials и origin](../auth/credentials.md) |
| `continuationTokenExtractor` | `?ContinuationTokenExtractorInterface` | [Ожидание и token](../execution/continuation-await.md) |
| `resultMetaExtractor` | `?ResultMetaExtractorInterface` | [Runtime meta результата](../results/handles.md#section-11) |
| `providerCatalogRegistry` | `?ProviderCatalogRegistryInterface` | [Каталоги SDK](catalogs.md) |
| `defaultContinuationMode` | `ContinuationMode` | [Состояние операции](../execution/continuation-state.md) |
| `defaultPollRequest` | `?string` | [Ожидание и token](../execution/continuation-await.md) |
| `continuationModeApplicator` | `?ContinuationModeApplicatorInterface` | [Состояние операции](../execution/continuation-state.md) |
| `redaction` | `RedactionPolicy` | [Логи и debug](../results/observability.md) |
| `textBooleanFormat` | `BooleanFormat` | [Значения частей запроса](../serialization/request-parts.md) |
| `originPolicy` | `OriginPolicy` | [Credentials и origin](../auth/credentials.md) |
| `includeClientQuota` | `bool` | [Квоты](../execution/rate-limit.md) |
| `rateLimitBackend` | `?RateLimitBackendInterface` | [Квоты](../execution/rate-limit.md) |
| `continuationStateResolver` | `?ContinuationStateResolverInterface` | [Состояние операции](../execution/continuation-state.md) |
| `localization` | `LocalizationConfig` &#124; `string` (свойство — `LocalizationConfig`) | [Язык сообщений и каталоги SDK](localization.md) |
| `hydration` | `?HydrationConfig` | [Общая политика и правила DTO](../dto/configuration.md) и [исходящий receiver](../serialization/receiver-output.md) |

## Граница конфигурации клиента <a id="section-4"></a>

Runtime-override отдельного запроса не меняет ClientConfig. Параметры выбираются
по тематическому контракту: единое правило «любой override всегда сильнее всего»
не заменяет особенности auth, накопления квот, wire-политики или внешних правил.

HTTP-кеш хранит ответ провайдера, не готовый DTO. Кеш метаданных — отдельный механизм;
его включение не должно менять изоляцию object defaults и аргументов атрибутов.
[Жизненный цикл DTO](../dto/lifecycle.md).

[Раздел клиента](README.md).

`with(cacheConfig: ...)` заменяет блок целиком, null удаляет его. Для изменения
отдельных полей передайте копию `CacheConfig::with(...)`. [Отключение и замена настроек](../execution/cache.md#section-3).

Точки расширения исполнения описаны в [контракте исполнителя клиента](../extensions/execution.md).
`throwOnErrors` управляет [окончательной публичной выдачей](../results/errors.md#section-2),
включая batch/pool/пагинацию, и не меняет внутренний сбор или решения о готовности.
`resultMetaExtractor` следует [жизненному циклу запроса](../results/handles.md#meta-lifecycle).


ClientConfig::cooldown по умолчанию — CooldownConfig(): автоматическая локальная координация после 429.
См. [поля, переопределения и пределы ожидания](../execution/cooldown.md).

ClientConfig::cooldownBackend по умолчанию null; явный LocalCooldownBackend или
PhpRedisCooldownBackend разделяет состояние совпадающих областей.
См. [контракт общего backend](../execution/cooldown.md#shared-backend).

`diagnosticLabel: ?string` — явная диагностическая метка клиента. Она попадает в
[observer-снимок](../results/observation.md#observer) и не участвует в выборе клиента,
credentials, ключах кеша, квот или cooldown.
