<!-- languages --> <a href="../../../en/reference/dto/models.md">English</a> · <a href="models.md">Русский</a> <!-- /languages -->
# Модели DTO и наследование <a id="section-1"></a>

Собственные модели могут полностью объявлять гидратацию [атрибутами](declarations.md), включая plain readonly-класс. DTO::from() читает эти декларации; общую политику клиента он не наследует — используйте Hydrator::forConfig().

Краткий гайд по DTO, маппингу и валидации.

Для моделей без атрибутов используйте [внешний набор правил](../../guides/dto/plain-models.md):
он задаёт mapping, дочерние DTO, strict, extras и проверку присутствия.
`DTO::from()` не наследует набор клиента; standalone-вход с набором — `Hydrator::forRules()`.

## Базовый DTO <a id="section-2"></a>
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

## Inheritance и hydration <a id="section-3"></a>
Hydrator в `apisutra` использует модель `constructor-first`, но поддерживает и inherited public properties вне constructor chain.

Это означает:
- всё, что покрывается effective constructor chain, инициализируется через конструктор
- оставшиеся публичные гидрируемые свойства могут быть доинициализированы hydrator-ом напрямую
- это особенно полезно для DTO-иерархий, где базовый класс держит общие поля, а конечный DTO добавляет свои блоки данных

Пример:
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

Практические правила:
- constructor-first остаётся основным и рекомендуемым контрактом
- fallback assignment работает только для оставшихся public data properties
- для nullable non-constructor property при `Missing` hydrator инициализирует `null`
- для non-nullable missing non-constructor property hydrator бросает явную ошибку

Конструктор может сам установить фиксированное свойство. Для проверки такого входа
без повторной записи используйте [constructorValue](constructor-values.md).

## Относительные native-типы <a id="section-4"></a>

`self` в свойстве означает класс, который объявил это свойство; `parent` — его
непосредственного родителя. При наследовании область объявления сохраняется.
Для свойства из trait это класс, в который PHP включил trait.

Например, фрагмент объявления рекурсивного DTO:

```php
use ApiSutra\DataTransfer\AbstractDto;

readonly class TreeNodeDto extends AbstractDto
{
    public function __construct(public ?self $child = null) {}
}

final readonly class BranchDto extends TreeNodeDto {}

$branch = BranchDto::from(['child' => ['child' => null]]);
// $branch — BranchDto, а $branch->child — TreeNodeDto: self объявлен в TreeNodeDto.
```

Это правило действует для одиночного типа и членов union: например,
`self|string|null` сохраняет строковую и nullable-ветви. Вложенный массив автоматически
становится DTO при поддержке DtoInterface, как при явном имени класса. Для plain-класса
нужна [декларация вложенного объекта](shapes.md) или пользовательский cast.

При поиске cast по PHP-типу используются полные имена классов после разрешения
`self`/`parent`. Регистрируйте cast по `TreeNodeDto::class`, а не по строке `'self'`.
Это относится к гидратации, DX/явной сериализации и query/body запроса;
приоритеты [источников casts](../serialization/casts.md) сохраняются.
