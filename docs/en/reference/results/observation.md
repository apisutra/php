<!-- languages --> <a href="observation.md">English</a> · <a href="../../../ru/reference/results/observation.md">Русский</a> <!-- /languages -->
# Execution observation <a id="observer"></a>

`ExecutionObserverInterface` resolves once through the selected ContainerProviderInterface,
only when bound. Without a binding no receiver or timer is created. `started(ExecutionTrace)`
marks entry, `completed(ExecutionSnapshot)` receives one terminal snapshot, and
`released(ExecutionTrace)` marks actual cleanup; cancellation can report a terminal earlier.
`includeAttempts(): bool` requests up to 32 details of the execution's own HTTP attempts.

An observer must not perform I/O, suspend a Fiber or run the event loop. Delivery belongs
to a safe application boundary after cleanup. Observer failures do not change SDK results.
The snapshot contains bounded safe fields: trace/execution/parent IDs, operation, role,
status, reason/stage, durationMs, attemptCount/httpDurationMs, method/origin and explicit
`ClientConfig::diagnosticLabel`. Bodies, full URL/query, headers, DTOs and exception text
are absent; redaction precedes observation. The label never affects auth/cache/quotas.

For sequential work, 1800 ms total − 120 ms own HTTP = 1680 ms outside own HTTP (also
hooks, hydration and nested auth). Concurrent child sums cannot be subtracted this way.
Roots have parentExecutionId === null; pages with role Root still have a parent.
PSR logging, audit and debug keep their existing contracts.
[Laravel events and delivery](https://github.com/apisutra/laravel/blob/master/docs/en/reference/integrations/observability.md) belong to the adapter.