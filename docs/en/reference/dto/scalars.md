<!-- languages --> <a href="scalars.md">English</a> · <a href="../../../ru/reference/dto/scalars.md">Русский</a> <!-- /languages -->
# Scalars, unions, and ranges <a id="section-1"></a>

Shared Strict without a registry:
`new HydrationConfig(policy: new RulePolicy(scalars: ScalarPolicy::Strict))`.
A model can explicitly override it with `#[DtoHydrate(scalars: ScalarPolicy::Legacy)]`;
see [configuration](configuration.md) for other priorities.

## Automatic casts by declared type <a id="section-2"></a>
In Legacy mode, the core applies safe conversions based on the declared property
type. Strict is set separately through external rules and has the narrower rules below.

Typical cases:
- `"12"` -> `12` for `int`
- `"12.5"` -> `12.5` for `float`
- `"true"` / `"false"` / `"1"` / `"0"` -> `bool`
- `42` -> `"42"` for `string`

This is a built-in fallback and does not replace `#[Cast]`.
Use explicit `#[Cast(...)]` for a nonstandard provider format or custom logic.

## Policy and strict types <a id="section-3"></a>

Policy follows [external rule-set priorities](field-rules.md#section-4).

| Target under `ScalarPolicy::Strict` | Accepted input |
| --- | --- |
| int | PHP int |
| float | PHP float; int from −2^53 through 2^53 inclusive, widened exactly |
| bool, true, false | Matching bool/literal |
| string | Only a string, including an empty string |
| scalar union | Exact branch first, preserving type; then int → float widening |
| mixed | Any value |

Numeric strings and bools do not become numbers in Strict. Integer overflow retains
reason `integer_out_of_range`; other mismatches use `invalid_field_type`. Nullability
is checked separately; DateTime and enums retain built-in conversions. Cast results
are also checked before reflection. `ScalarPolicy::Legacy` allows scalar
conversions. A rule set uses Legacy by default.

## Large integers in responses <a id="section-4"></a>

An integer JSON literal outside the PHP int range is automatically preserved as a
string with exact digits. Within range, it remains an int. This works in standard
response parsing, `ProviderResponse::json()`/`jsonStrict()`, and nested JSON through
`JsonCast`. No client setting is needed.

For example, `{"id":9223372036854775808}` on 64-bit PHP produces string
`"9223372036854775808"`. Use `string` or `int|string` for DTO identifiers:

```php
use ApiSutra\DataTransfer\AbstractDto;

final readonly class OrderIdentifierDto extends AbstractDto
{
    public function __construct(public string $id) {}
}
```

In Legacy, `string` also accepts an ordinary integer ID; `int|string` retains int
for ordinary JSON integers and string for large ones. Mixed and arrays preserve
the decoding result. With `int`/`?int`, an out-of-range number produces `hydration_error`
instead of clamping to PHP_INT_MAX/MIN. New errors expose a
[safe field path](../results/errors.md#section-12).

JSON strings remain strings during parsing. Fractions and exponential notation still
decode as float: exact decimal arithmetic is not provided. A float type or FloatCast
selects an approximate representation. The SDK cannot recover numbers already rounded
by the server or caller. On subsequent sending, a string ID remains a JSON string;
it is not automatically converted to a numeric literal.

This changes previous behavior, where large integers could return as float.
Review DTO int fields, custom json() handlers, and old fixtures. Select approximate
arithmetic explicitly; keep identifiers as strings.
