<!-- languages --> <a href="extras.md">English</a> · <a href="../../../ru/reference/dto/extras.md">Русский</a> <!-- /languages -->
# Preserving additional data <a id="section-1"></a>

For your own model, mark a declared property with `#[Extras]`; the external equivalent
is `DtoRules::extras()`. No class registry is needed. The wire serializer also finds
the attribute in manually created and nested DTOs before the first hydration;
ordinary toArray() retains the receiver.

## Additional fields <a id="section-2"></a>

The recommended technical property name is `_extra`. Mark it with `#[Extras]` or
declare it through external `DtoRules::extras()`. The core does not reserve this name:
existing models using `extra` or another name continue to work with their declaration.

`extras('_extra')` stores data the core has not read. The receiver must be a public
array or ?array: a parameter of the single constructor, or a property of a class
without a constructor. Static/virtual/non-public receivers, incompatible types,
a separate FieldRule, and receiver input attributes are forbidden. A parameter default is allowed.

Only the selected primary/fallback is consumed. Original null counts as read.
Overlapping paths are combined: reading a parent consumes its entire subtree; field
order does not affect the result. Empty consumed branches are removed; untouched
false, 0, null, empty strings, and arrays are preserved. Arbitrary keys read by a
provider do not count as consumed. A source key matching the receiver name stays
inside `_extra` instead of populating it directly.

For example, for the example's `EntryDto`, source
`{"record_id": 7, "extra": {"enabled": true}, "_extra": "remote"}` produces
`id = 7` and `_extra = ['extra' => ['enabled' => true], '_extra' => 'remote']`.
Both source keys are preserved. A key read by another field's rule is already consumed
and does not enter the remainder. The `_` prefix only visually distinguishes the
technical property; this processing order protects against name collisions.

For an each projection, siblings of the selected value remain with the list owner. Example:

```json
{
  "rows": [
    {"sourceKey": 0, "remainder": {"meta": {"revision": 2}}}
  ],
  "next_feature": null
}
```

Each projection uses a dense list of `{sourceKey, remainder}` records regardless of
skipped items. For a list of lists, remainder recursively uses the same record list.
sourceKey retains the PHP key type **after** JSON decoding: `"1"` becomes int 1,
while `"01"` remains a string. This cannot distinguish a numeric object name from a list index.

A Value discriminator remains in the child DTO's `_extra` unless mapped to a field.
In Key mode, the child DTO receives the selected wrapper's contents; its siblings
remain in the list remainder. KeepRaw preserves the entire unknown item; each siblings
remain with the parent. Skip consumes the whole item, including each siblings.
