<!-- languages --> <a href="lifecycle.md">English</a> · <a href="../../../ru/reference/dto/lifecycle.md">Русский</a> <!-- /languages -->
# Hydration lifecycle and object isolation <a id="section-1"></a>

The hydration cache stores a plan for working with a class, not input values or
completed DTOs. Different hydrators' rules remain isolated even with a shared metadata
cache. Profiles resolve for each node: warming the cache does not freeze policy or registry.

## Object arguments in attributes <a id="section-2"></a>

Objects from `new` expressions in `#[Cast]` arguments, including nested arrays, are
not retained as shared instances in the metadata cache. The attribute is instantiated
anew for each processed DTO or request property; its handler receives arguments for
that application. This applies in Hydrator, DtoSerializer, and query/body assembly
through Serializer, including nested wire DTOs.

Arguments are evaluated when reading the current node's attributes, even when the
property is missing, null, or its handler is later skipped. The first instance is
used immediately, without a second trial evaluation. Scalar, null, enum, and arrays
containing only those values may be reused from the cache.

Object-valued `DefaultValue` arguments follow the same rule. Intentionally supplied
cast instances in profiles, `ClientConfig`, and registries retain their shared-object
semantics. `Nested(itemCast:)` still creates one handler per list traversal.
This rule does not add Cast support to header/path/file.

## Defaults and object isolation <a id="section-3"></a>

If a constructor parameter value is not resolved from input, attributes, or an
automatic collection default, the hydrator omits the argument. PHP evaluates its
default during the single constructor call. `new` and arrays containing objects in
defaults create independent values for every DTO, including collection items and
nested DTOs. If a value is resolved, including allowed null, the default is not
evaluated. An exception from the default expression propagates like a DTO constructor exception.

This is the same for `DTO::from()`, direct Hydrator use, Returns, pagination,
CompositeFlow, and await, with or without metadata caching. Objects in
`#[DefaultValue(value: [new ...])]` are also created anew for every hydrated DTO.
See the [cast reference](lifecycle.md#section-2) for attribute object argument evaluation.

Objects passed in the input by calling code retain their identity: the hydrator
does not copy them automatically. Intentionally shared profile cast instances and
custom handlers' static state are not isolated either.

## computed() for Response DTOs <a id="section-4"></a>
`AbstractResponseDto` allows data to be modified before hydration:
```php
final readonly class UserDto extends AbstractResponseDto
{
    public static function computed(array $data, ?PipelineContext $context = null): array
    {
        $data['fullName'] = trim(($data['first_name'] ?? '') . ' ' . ($data['last_name'] ?? ''));
        return $data;
    }
}
```

## Traversal limits <a id="section-5"></a>

The hydrator limits active DTO/list nodes to depth 512 (root = 1). Sibling branches
are not added together. A cycle causes an error; revisiting a shared object through
an independent branch is allowed. The error receives the current path;
[diagnostics](diagnostics.md) distinguishes the DTO path from the original sourcePath.

Client metadata caching is enabled in Production/Staging and disabled in Local/Testing.
Value isolation is identical in both modes, including for a long-lived client.

Recursive DTO declarations are validated as a group before use. If custom autoload
suspends compilation, external re-entry into an unfinished class through the same
rule owner receives `ConfigurationException`; no partially validated description is
exposed. Ordinary nested hydration and reading completed descriptions are allowed.
Declaration errors are not cached: the next call repeats validation.

For whole-object construction, use a [custom hydrator](hydrators.md); its supported nodes bypass native field rules.
With a global hydrator, external rules are compiled on native use instead of eagerly;
see [ownership and deferred validation](hydrators.md#context).
