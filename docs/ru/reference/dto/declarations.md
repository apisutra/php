<!-- languages --> <a href="../../../en/reference/dto/declarations.md">English</a> · <a href="declarations.md">Русский</a> <!-- /languages -->
# Атрибутные декларации DTO <a id="section-1"></a>

Когда модели принадлежат SDK, объявляйте обработку поля на самой модели.
Работают plain-классы, readonly и DTO ApiSutra; реестр с withDto() не нужен.
[Общий HydrationConfig](configuration.md) задаёт политику всему обходу.

```php
use ApiSutra\Attributes\DataTransfer\ConstructorValue;
use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Attributes\DataTransfer\ForbidExplicitNull;
use ApiSutra\Attributes\DataTransfer\From;
use ApiSutra\Attributes\DataTransfer\RequiredInput;
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Rules\ScalarType;
use ApiSutra\Serialization\Shapes\ListShape;

final readonly class Record
{
    #[ConstructorValue]
    public string $kind;

    public function __construct(
        #[From('record_id')] #[RequiredInput] public int $id,
        #[Shape(new ListShape(new ListShape(ScalarType::Int)))] public array $rows = [],
        #[ForbidExplicitNull] public ?int $stock = null,
        #[Extras] public array $_extra = [],
    ) {
        $this->kind = 'record';
    }
}
```

При общей Strict-policy `{kind:"record",record_id:7,rows:[[1,2],[]],future:false}`
даёт id=7, исходные rows, stock=null, `_extra=['future'=>false]`.
Конструктор вызывается один раз. `record_id:"7"`, неправильный kind, найденный
stock:null или строковый элемент rows — ошибки с DTO-path и исходным JSON Pointer.
Полный исполняемый [обзор](../../guides/dto/showcase.md) показывает вход, результат и запрос.

| Атрибут | Эквивалент внешних правил | Полный контракт |
| --- | --- | --- |
| Extras | DtoRules::extras с именем свойства | [Остаток входа и коллизии](extras.md) |
| ConstructorValue(allowMissing: false) | FieldRule::constructorValue | [Сравнение с конструктором](constructor-values.md) |
| RequiredInput | FieldRule::required | [Присутствие до defaults](defaults.md) |
| ForbidExplicitNull | FieldRule::forbidExplicitNull | [Отсутствие и null](defaults.md) |
| Shape | FieldRule::shape | [Формы значений](shapes.md) |

Все пять атрибутов неповторяемые и относятся к свойству. Promotion читается как
декларация свойства, без повторного исполнения на параметре. Имя receiver произвольно;
оно объявляется явно, а не добавляется динамически. Входной ключ `_extra` сохраняется
внутри остатка и не заменяет receiver. В запросах клиента receiver исключается также
у вручную созданных и вложенных моделей. Dump через toArray() его сохраняет.

## Конструкторные формы <a id="section-2"></a>

Shape принимает `ScalarType|ShapeSpec`; узлы лежат в `Serialization\Shapes`.
Они переводятся в существующий ValueShape, с той же обработкой и ошибками.

| Узел | Конструктор |
| --- | --- |
| ScalarType | Int, Float, Bool, String — один скалярный тип |
| ListShape | `ListShape(ScalarType\|ShapeSpec $item, ?string $each = null, ?HandlerSpec $itemCast = null, bool $normalizeKeys = false)` |
| NullableShape | `NullableShape(ScalarType\|ShapeSpec $value)` |
| DtoShape | `DtoShape(string $class, bool $emptyListAsObject = false)` — в том числе plain-класс |
| VariantsShape | `VariantsShape(string $discriminator, array $map, NestedDiscriminatorMode $mode = NestedDiscriminatorMode::Value, NestedUnknownVariant $unknown = NestedUnknownVariant::KeepRaw)` |

Nullable задаётся отдельно для списка и элемента. Например,
`new NullableShape(new ListShape(new NullableShape(ScalarType::Int)))` допускает
null, пустой список и элементы null. Словари и sparse list отклоняются; преобразование
ключей разрешает только явный normalizeKeys. each/itemCast принадлежат конкретному
ListShape, поэтому их можно задавать и на внутреннем уровне списка.

В атрибуте допустимы вложенные new и enum; фабрики ValueShape там вызвать нельзя.
Сторонний ShapeSpec отклоняется, пользовательский compiler не исполняется.
Mixed, scalar-литералы true/false и scalar union в языке Shape доступны только
через внешние ValueShape; native PHP mixed/union/литералы используют native-проверки.

## Сочетания <a id="section-3"></a>

From/Map совместимы со всеми проверками. RequiredInput выполняется раньше
DefaultValue; ForbidExplicitNull раньше provider для Null. RequiredInput также
не отменяется ConstructorValue(allowMissing: true).

Shape + Nested или Cast одного поля — ConfigurationException. Для преобразования
элемента есть itemCast; внешнее FieldRule::cast сохраняет отдельную result shape.
Nested сам по себе не включает строгую проверку формы списка; сочетания атрибутов определяют преобразование.

Внешний FieldRule и входной атрибут того же поля конфликтуют, даже если совпадают.
Соседние поля могут использовать разные способы. Два receivers, внешнее extras
вместе с Extras или входной/исходящий атрибут на receiver также отклоняются.
Ограничения receiver и профилей — в [правилах конфигурации](field-rules.md).

Добавление атрибута не переключает raw items-only пагинацию в DTO: для этого нужен
явный HydrationConfig. [Почему и как включить типизацию](../execution/pagination.md).
