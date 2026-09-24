<!-- languages --> <a href="../../en/examples/hydration-rules.md">English</a> · <a href="hydration-rules.md">Русский</a> <!-- /languages -->
# Пример внешних правил DTO <a id="section-1"></a>

Запуск из checkout после `composer install`:

```bash
php docs/example/hydration-rules/run.php
```

В установленном пакете добавьте `vendor/apisutra/php/` перед путём.
[run.php](../../example/hydration-rules/run.php) загружает модели, строит набор и преобразует один вложенный ответ.

- [EntryDto](../../example/hydration-rules/src/EntryDto.php) — поле id и receiver `_extra`.
- [ReportDto](../../example/hydration-rules/src/ReportDto.php) — owner, список DTO, список int и nullable count.
- [EntryCast](../../example/hydration-rules/src/EntryCast.php) — вложенная гидратация через текущий scope.

Ожидаются owner.id = 7, items[0].id = 8, ids = `[1, 2]`, count = null.
Owner сохраняет `future: false`; корневой receiver сохраняет `next_feature: null`
и соседнюю `meta.revision: 2` у первого элемента rows.

Smoke исполняет эти исходники и отдельно проверяет: отказ для строки в strict int,
DTO-путь `items[1].id`, sourcePath `/rows/1/value/record_id`, одинаковый набор в
`Returns`, исключение receiver из wire, коллизию исходных extra/_extra и scoped cast.
Laravel не загружается.

[Практическое руководство](../guides/dto/plain-models.md) ·
[Полные правила](../reference/dto/field-rules.md) ·
[Extras](../reference/dto/extras.md) · [Scope](../reference/dto/scope.md).
