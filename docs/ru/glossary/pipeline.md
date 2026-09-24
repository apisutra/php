<!-- languages --> <a href="../../en/glossary/pipeline.md">English</a> · <a href="pipeline.md">Русский</a> <!-- /languages -->
# Pipeline <a id="section-1"></a>

| Термин | Значение | Подробнее |
| --- | --- | --- |
| <a id="pipelinecontext"></a> PipelineContext | Контейнер контекста выполнения. | [Контракт](../reference/extensions/hooks.md) |
| <a id="requestrole"></a> RequestRole | Роль исполнения: корневой запрос, вложенный запрос или подготовительная зависимость. | [Контракт](../reference/request/composition.md) |
| <a id="serializer"></a> Serializer | Сервис сериализации запроса в PreparedRequest. | [Контракт](../reference/serialization/request-parts.md) |
| <a id="request-lifecycle"></a> Request Lifecycle | Последовательность хуков, через которую проходит каждый запрос: beforeSend, HTTP-вызов, afterResponse, beforeHydrate, hydrate, afterHydrate. | [Контракт](../reference/extensions/hooks.md) |
| <a id="hook-enum"></a> Hook (enum) | Enum типов хуков: BeforeSend, AfterResponse, BeforeHydrate, AfterHydrate. | [Контракт](../reference/extensions/hooks.md) |
| <a id="hookpriority-enum"></a> HookPriority (enum) | Enum приоритета выполнения hook. | [Контракт](../reference/extensions/hooks.md) |
| <a id="hookregistry"></a> HookRegistry | Сервис для централизованной регистрации обработчиков хуков. | [Контракт](../reference/extensions/hooks.md) |
| <a id="lifecycle-hooks"></a> Lifecycle Hooks | Методы жизненного цикла запроса: beforeSend, afterResponse, beforeHydrate, afterHydrate. | [Контракт](../reference/extensions/hooks.md) |
| <a id="hookinterface"></a> HookInterface | Базовый интерфейс для всех хуков. | [Контракт](../reference/extensions/hooks.md) |
| <a id="beforesendhookinterface"></a> BeforeSendHookInterface | Интерфейс для классов-обработчиков хука beforeSend. | [Контракт](../reference/extensions/hooks.md) |
| <a id="afterresponsehookinterface"></a> AfterResponseHookInterface | Интерфейс для классов-обработчиков хука afterResponse. | [Контракт](../reference/extensions/hooks.md) |
| <a id="beforehydratehookinterface"></a> BeforeHydrateHookInterface | Интерфейс для модификации данных до гидрации. | [Контракт](../reference/extensions/hooks.md) |
| <a id="afterhydratehookinterface"></a> AfterHydrateHookInterface | Интерфейс для обработки после создания DTO. | [Контракт](../reference/extensions/hooks.md) |
| <a id="traceid"></a> TraceId | Уникальный идентификатор (UUID), связывающий все события одной операции. | [Контракт](../reference/results/observability.md) |
| <a id="pipelineevent"></a> PipelineEvent | Value Object события audit log. | [Контракт](../reference/results/observability.md) |
| <a id="pipelinestage"></a> PipelineStage | Стадия выполнения в событии pipeline: от запуска и HTTP до гидратации, завершения или ошибки. | [Контракт](../reference/results/observability.md) |
| <a id="debuginfo"></a> DebugInfo | Value Object, агрегирующий debug-информацию. | [Контракт](../reference/results/observability.md) |

[Все термины](README.md).
