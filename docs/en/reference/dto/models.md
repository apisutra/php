<!-- languages --> <a href="models.md">English</a> · <a href="../../../ru/reference/dto/models.md">Русский</a> <!-- /languages -->
# DTO models and inheritance <a id="section-1"></a>

Your models can declare all hydration through [attributes](declarations.md), including
plain readonly classes. `DTO::from()` reads these declarations but does not inherit
shared client policy; use Hydrator::forConfig().

A short guide to DTOs, mapping, and validation.

For models without attributes, use an [external rule set](../../guides/dto/plain-models.md):
it defines mapping, child DTOs, Strict, extras, and presence checks.
`DTO::from()` does not inherit client rules; use `Hydrator::forRules()` for standalone hydration with rules.

## Basic DTO <a id="section-2"></a>
```php
use ApiSutra\DataTransfer\AbstractResponseDto;

final readonly class UserDto extends AbstractResponseDto
{
    public function __construct(
        public int $id,
        public string $name,
    ) {}
}
```

## Inheritance and hydration <a id="section-3"></a>
The `apisutra` Hydrator uses a `constructor-first` model and also supports inherited
public properties outside the constructor chain.

This means:
- Everything covered by the effective constructor chain is initialized through the constructor.
- Remaining public hydratable properties can be initialized directly by the hydrator.
- This is especially useful in DTO hierarchies where the base class holds shared
  fields and the final DTO adds its own data blocks.

Example:
```php
abstract readonly class BaseBlockDto extends AbstractResponseDto
{
    public function __construct(
        public ReportFoundState $found,
        public ReportBlockStatus $status,
    ) {}
}

final readonly class OtherLastNamesBlockDto extends BaseBlockDto
{
    #[From('result')]
    public ?OtherLastNamesResultDto $result;
}
```

Practical rules:
- Constructor-first remains the primary, recommended contract.
- Fallback assignment applies only to remaining public data properties.
- For a nullable non-constructor property, Missing initializes it to `null`.
- A missing non-nullable non-constructor property causes an explicit error.

The constructor may set a fixed property itself. To validate that input without
writing again, use [constructorValue](constructor-values.md).

## Relative native types <a id="section-4"></a>

`self` in a property means the class declaring that property; `parent` means that
class's immediate `parent`. Inheritance preserves the declaring scope. For a property
from a trait, this is the class into which PHP incorporated the trait.

For example, this recursive DTO declaration:

```php
use ApiSutra\DataTransfer\AbstractDto;

readonly class TreeNodeDto extends AbstractDto
{
    public function __construct(public ?self $child = null) {}
}

final readonly class BranchDto extends TreeNodeDto {}

$branch = BranchDto::from(['child' => ['child' => null]]);
// $branch is BranchDto and $branch->child is TreeNodeDto: self is declared in TreeNodeDto.
```

This rule applies to single types and union members: for example, `self|string|null`
retains string and nullable branches. A nested array automatically becomes a DTO when
DtoInterface is supported, as with an explicit class name. A plain class requires a
[nested object declaration](shapes.md) or a custom cast.

PHP-type cast lookup uses fully qualified class names after resolving `self`/`parent`.
Register a cast for `TreeNodeDto::class`, not the string `'self'`. This applies to
hydration, DX/explicit serialization, and request query/body;
[cast source priorities](../serialization/casts.md) remain unchanged.
