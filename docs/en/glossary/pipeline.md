<!-- languages --> <a href="pipeline.md">English</a> · <a href="../../ru/glossary/pipeline.md">Русский</a> <!-- /languages -->
# Pipeline <a id="section-1"></a>

| Term | Meaning | Details |
| --- | --- | --- |
| <a id="pipelinecontext"></a> PipelineContext | An execution context container. | [Contract](../reference/extensions/hooks.md) |
| <a id="requestrole"></a> RequestRole | The execution role: root request, nested request, or preparatory dependency. | [Contract](../reference/request/composition.md) |
| <a id="serializer"></a> Serializer | A service that serializes a request into PreparedRequest. | [Contract](../reference/serialization/request-parts.md) |
| <a id="request-lifecycle"></a> Request Lifecycle | The sequence each request passes through: beforeSend, HTTP call, afterResponse, beforeHydrate, hydrate, afterHydrate. | [Contract](../reference/extensions/hooks.md) |
| <a id="hook-enum"></a> Hook (enum) | The hook type enum: BeforeSend, AfterResponse, BeforeHydrate, AfterHydrate. | [Contract](../reference/extensions/hooks.md) |
| <a id="hookpriority-enum"></a> HookPriority (enum) | The hook execution priority enum. | [Contract](../reference/extensions/hooks.md) |
| <a id="hookregistry"></a> HookRegistry | A service for centralized registration of hook handlers. | [Contract](../reference/extensions/hooks.md) |
| <a id="lifecycle-hooks"></a> Lifecycle Hooks | Request lifecycle methods: beforeSend, afterResponse, beforeHydrate, afterHydrate. | [Contract](../reference/extensions/hooks.md) |
| <a id="hookinterface"></a> HookInterface | The base interface for all hooks. | [Contract](../reference/extensions/hooks.md) |
| <a id="beforesendhookinterface"></a> BeforeSendHookInterface | An interface for beforeSend hook handler classes. | [Contract](../reference/extensions/hooks.md) |
| <a id="afterresponsehookinterface"></a> AfterResponseHookInterface | An interface for afterResponse hook handler classes. | [Contract](../reference/extensions/hooks.md) |
| <a id="beforehydratehookinterface"></a> BeforeHydrateHookInterface | An interface for modifying data before hydration. | [Contract](../reference/extensions/hooks.md) |
| <a id="afterhydratehookinterface"></a> AfterHydrateHookInterface | An interface for processing after DTO creation. | [Contract](../reference/extensions/hooks.md) |
| <a id="traceid"></a> TraceId | A unique identifier (UUID) that links all events of one operation. | [Contract](../reference/results/observability.md) |
| <a id="pipelineevent"></a> PipelineEvent | A value object for an audit log event. | [Contract](../reference/results/observability.md) |
| <a id="pipelinestage"></a> PipelineStage | The execution stage in a pipeline event: from start and HTTP to hydration, completion, or error. | [Contract](../reference/results/observability.md) |
| <a id="debuginfo"></a> DebugInfo | A value object that aggregates debug information. | [Contract](../reference/results/observability.md) |

[All terms](README.md).
