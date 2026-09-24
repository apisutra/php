<!-- languages --> <a href="hydration-rules.md">English</a> · <a href="../../ru/examples/hydration-rules.md">Русский</a> <!-- /languages -->
# External DTO rules example <a id="section-1"></a>

Run from the checkout after `composer install`:

```bash
php docs/example/hydration-rules/run.php
```

For an installed package, prefix the path with `vendor/apisutra/php/`. [run.php](../../example/hydration-rules/run.php) loads models, builds a rule set, and transforms one nested response.

- [EntryDto](../../example/hydration-rules/src/EntryDto.php): id field and `_extra` receiver.
- [ReportDto](../../example/hydration-rules/src/ReportDto.php): owner, DTO list, int list, and nullable count.
- [EntryCast](../../example/hydration-rules/src/EntryCast.php): nested hydration through the current scope.

Expected: owner.id = 7, items[0].id = 8, ids = `[1, 2]`, count = null. Owner retains `future: false`; the root receiver retains `next_feature: null` and the adjacent `meta.revision: 2` in the first rows element.

The smoke test executes these source files and separately checks rejection of a string for strict int, DTO path `items[1].id`, sourcePath `/rows/1/value/record_id`, the same rule set in `Returns`, receiver exclusion from wire output, collisions with original extra/_extra keys, and the scoped cast. Laravel is not loaded.

[Practical guide](../guides/dto/plain-models.md) · [Full rules](../reference/dto/field-rules.md) · [Extras](../reference/dto/extras.md) · [Scope](../reference/dto/scope.md).
