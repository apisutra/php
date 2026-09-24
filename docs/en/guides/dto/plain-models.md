<!-- languages --> <a href="plain-models.md">English</a> · <a href="../../../ru/guides/dto/plain-models.md">Русский</a> <!-- /languages -->
# DTOs without attributes <a id="section-1"></a>

To use models without ApiSutra attributes, build immutable `HydrationRules`
and pass them as `ClientConfig(hydration: new HydrationConfig(rules: $rules), ...)`.
One rules object describes mapping, nested DTOs, strict types, field presence, and
retention of additional data. For processing without a client, use `Hydrator::forRules($rules)`:
Laravel, a container, and HTTP are unnecessary.

## End-to-end example <a id="section-2"></a>

The [published example](../../examples/hydration-rules.md) contains separate DTO files,
a rules object, and a scoped cast. Run it from a checkout after Composer install:

```bash
php docs/example/hydration-rules/run.php
```

In an installed package, prefix the path with `vendor/apisutra/php/`.
[run.php](../../../example/hydration-rules/run.php) builds one rules object for standalone
use and `ClientConfig`. It describes owner, a `rows` list with `each: 'value'` projection,
a strict ids list, explicit-null rejection for count, and the `_extra` receiver.

Expected: owner.id = 7, owner._extra = `['future' => false]`, items[0].id = 8,
count = null. Root `_extra` retains `next_feature: null` and sibling meta fields
of rows elements. The [remainder shape](../../reference/dto/extras.md) is covered separately.

Pass `$config` to your SDK client. `#[Returns(ReportDto::class)]` uses the same rules.
The `Hydrator`, `DtoSerializer`, and `Serializer` constructors also accept
optional `config: new HydrationConfig(rules: $rules)`. The client passes the rules to both directions.
`$config->with(hydration: null)` creates configuration without the rules;
`with()` without an override preserves the original rules.

`Hydrator::default()` and `DTO::from()` do not inherit client rules. Pass the same rules
explicitly for matching standalone and client behavior. In Laravel, assemble rules
in a provider/factory rather than storing live descriptors in cacheable configuration.

## Applying rules to an SDK <a id="section-3"></a>

Existing attribute recipes remain available: a [Present/Null provider](../../reference/dto/defaults.md#section-9)
checks null rejection and shape before Nested; `Nested(itemCast:)` can check a scalar
element or return a raw object through a factory. Such itemCast instances are created
without arguments, so a parameterized strict scalar cast requires a separate class.
Bare arrays and PHPDoc do not check elements. `list(list(dto(...)))` replaces RowCast
for a two-dimensional list without a separate row DTO. A raw factory that constructs
an object directly does not call the hydrator.

Do not combine conflicting input attributes with an external FieldRule for the same field.
Inside casts/providers, use the received context for nested hydration. A raw factory
constructs objects independently of the hydrator. Choose strict mode according to the
actual JSON types. A client with rules excludes the receiver from outgoing data;
a whole-object cast over a visible receiver is forbidden. See
[readiness rules](../../reference/execution/continuation-await.md#section-10) for continuation results.

For models whose constructors set `type` or fixed arrays themselves, use
[constructorValue](../../reference/dto/constructor-values.md): input is checked after
normal transformations, and the readonly property is not written again.

An existing domain factory can instead implement a [custom DTO hydrator](../../reference/dto/hydrators.md), including private constructors and application dependencies.
