<!-- languages --> <a href="../../../en/reference/results/observation.md">English</a> · <a href="observation.md">Русский</a> <!-- /languages -->
# Наблюдение за исполнением <a id="observer"></a>

`ExecutionObserverInterface` разрешается один раз через выбранный ContainerProviderInterface,
только при наличии binding. Без binding получателя и таймеров нет. `started(ExecutionTrace)`
отмечает начало, `completed(ExecutionSnapshot)` получает единственный терминал,
`released(ExecutionTrace)` — фактический cleanup; отмена может дать терминал раньше cleanup.
`includeAttempts(): bool` запрашивает до 32 подробностей собственных HTTP-попыток.

Observer не выполняет I/O, не уступает Fiber и не запускает event loop; для доставки
приложение использует безопасную границу после cleanup. Ошибка observer не меняет SDK-результат.
Снимок содержит только ограниченные безопасные поля: trace/execution/parent ID, operation,
role, status, reason/stage, durationMs, attemptCount/httpDurationMs, method/origin
и явный `ClientConfig::diagnosticLabel`. Тела, URL/query, headers, DTO и тексты исключений
не передаются; redaction выполняется до observer. Метка не влияет на auth/cache/квоты.

Для последовательного исполнения 1800 мс всего − 120 мс собственного HTTP = 1680 мс
вне собственного HTTP (включая hooks, гидратацию и вложенный auth). Суммы конкурентных
детей так вычитать нельзя. Root определяется по parentExecutionId === null; страницы
с ролью Root всё равно имеют родителя. PSR-логгер, audit и debug сохраняют прежние контракты.
[События и доставка Laravel](https://github.com/apisutra/laravel/blob/master/docs/ru/reference/integrations/observability.md) описаны в адаптере.