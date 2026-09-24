<!-- languages --> <a href="profiles.md">English</a> · <a href="../../../ru/reference/dto/profiles.md">Русский</a> <!-- /languages -->
# Field names and hydration profiles <a id="section-1"></a>

## Field mapping <a id="section-2"></a>
Use mapping when response/request keys differ from property names.
- `Map` uses the same external key for hydration and serialization.
- `From` maps incoming data (API responses).
- `To` maps outgoing data (request serialization).
```php
use ApiSutra\Attributes\DataTransfer\Map;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\To;

#[Map('user_id')]
public int $userId;

#[From('data.user_id')]
public int $id;

#[To('user_id')]
public int $id;
```

### Choosing a declaration <a id="section-3"></a>

| Scenario | Use | Why |
|---|---|---|
| Same external key for input and output | `#[Map('user_id')]` | Avoids duplicating `From` + `To` |
| Input mapping only | `#[From('data.user_id')]` | Supports dot paths and fallback |
| Output mapping only | `#[To('user_id')]` | Explicitly controls serialization |
| Different input and output | `#[From(...)]` + `#[To(...)]` | Directions are independent |
| `Map` exists but one direction needs an override | `Map` + `From` or `Map` + `To` | `From` takes priority for hydration, `To` for serialization |
| Ordinary camelCase <-> snake_case without exceptions | `NamingStrategy::SnakeCase` | No explicit attributes needed |

Priorities:
- Hydration: `From` -> `Map` -> `NamingStrategy`
- Serialization: `To` -> `Map` -> `NamingStrategy`

## Profile for an attribute-based model <a id="section-4"></a>

`DtoHydrationProfileInterface` lives in `Contracts\Interfaces\Serialization`.
It returns `DtoHydrationPolicy` through `policy()` and casts by PHP type through `casts()`.
`#[DtoHydrationProfile(Profile::class)]` binds a profile to a DTO or its shared base;
the profile class is instantiated without arguments. Lookup proceeds from the concrete
class to its parents: the nearest profile and, separately, the nearest `DtoHydrate` override are selected.

`DtoHydrationPolicy` defaults to `NamingStrategy::None`, `EmptyStringBehavior::Keep`,
and the standard `DateTimeHydrationPolicy`. `DtoHydrate` overrides only non-null
parameters on top of the resolved profile. The shared AbstractDto/AbstractResponseDto
bases do not attach a profile themselves.

Profile casts accept an instance of a directional HydrationCastInterface/SerializationCastInterface
or a class with a no-argument constructor. A field Cast attribute takes precedence
over a profile cast. Global and client CastRegistry are not used for input hydration.
See the [complete transformation selection](../serialization/casts.md).

With external rules, RulePolicy is declared without attributes. Combining DtoRules
with a class input profile is forbidden; a class outside DtoRules retains its complete
profile and ignores rule-set defaults. See [conflicts and priorities](field-rules.md).

## Input date policy <a id="section-5"></a>

`DateTimeHydrationPolicy` lives in `ApiSutra\Config`.

| Parameter | Default | Meaning |
| --- | --- | --- |
| `format` | `DATE_ATOM` | Expected date format |
| `defaultTimezone` | `'UTC'` | Time zone for a value without an offset |
| `preserveOffset` | `true` | Preserve the input value's offset |
| `strictMissingTimezone` | `false` | Require an explicit time zone |
| `strictFormat` | `false` | Require the format to match |
| `invalidBehavior` | `DateTimeInvalidBehavior::Throw` | Response to an unparseable date |

A field-level DateTimeFrom overrides date policy. Union handling must not be used to
hide an invalid format: see [type selection](scalars.md).
Outgoing dates are covered separately in [DTO output](../serialization/dto-output.md).

## Parsing input dates <a id="section-6"></a>
`DateTimeInterface` fields are parsed according to hydration policy:
- `format` is the preferred format for `createFromFormat`.
- If `format` does not match, `strictFormat=true` produces an error without fallback;
  `strictFormat=false` falls back to `DateTimeImmutable($value, $defaultTimezone)`.
- `defaultTimezone` applies **only** when input has no offset.
- `preserveOffset=true` retains the string's offset; `false` converts to `defaultTimezone`.
- `strictMissingTimezone=true` treats a missing offset as an error.
- `invalidBehavior` controls failure handling: `Throw` throws an exception; `Null` returns `null`.

The field's native type must allow the result of `invalidBehavior=Null`. Otherwise,
post-cast validation produces `HydrationException` with reason `null_not_allowed`.
DefaultValue applies before transformation and does not run again for the resulting null.
