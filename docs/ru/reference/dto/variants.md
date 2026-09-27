<!-- languages --> <a href="../../../en/reference/dto/variants.md">English</a> · <a href="variants.md">Русский</a> <!-- /languages -->
# Варианты DTO <a id="section-1"></a>

## Discriminator <a id="section-2"></a>

`ValueShape::variants(string $discriminator, array $map, DiscriminatorMode $mode = Value,
UnknownVariant|string $unknown = KeepRaw)` описывает одно значение-вариант отдельно
или внутри list(). VariantsShape — атрибутный эквивалент; Nested использует те же
правила выбора. Enum находятся в `ApiSutra\Enums\DataTransfer`.

Value выбирает класс по string/int по пути, например `event.type`. Missing/null
использует unknown-политику; bool, float, array и object (включая Stringable) дают
`invalid_discriminator_type`, без fallback и неявного приведения к строке.
Ключи PHP 1 и "1" совпадают; "01" отличается. Key использует первый ключ wrapper
(пустой discriminator означает текущий объект), в том числе по вложенному пути.

Неизвестный допустимый тег обрабатывается через KeepRaw, Skip, Error или класс
fallback DTO. Error даёт `unknown_nested_variant`. Ошибка известной модели никогда
не запускает fallback. Fallback получает весь узел после each, включая неизвестный
тег или Key-wrapper; известная Key-модель получает свой payload. JSON-форма сохраняется.
Extras у fallback сохраняет PHP-значения, не исходный текст JSON.

| Позиция | Default | Допустимые unknown-политики |
| --- | --- | --- |
| Элемент списка в Shape | KeepRaw | KeepRaw, Skip, Error, fallback-класс; типизированные DTO-коллекции запрещают KeepRaw |
| Элемент списка в Nested | KeepRaw | KeepRaw, Skip, Error, fallback-класс; типизированная коллекция отклоняет raw-элемент при появлении неизвестного варианта |
| DtoVariants / withVariants | Error | Error или совместимый fallback-класс |
| Одиночный VariantsShape | KeepRaw | Error или fallback; KeepRaw только при native-типе поля, допускающем raw-массивы; Skip запрещён |

У типизированной коллекции все классы map и fallback должны соответствовать
её декларации itemClass. Shape проверяет это при компиляции поля; Nested — перед
обработкой элементов, даже если во входе только известные теги. Создавать коллекцию
для этой проверки не требуется.
Nested сохраняет default KeepRaw: известные теги работают в типизированной коллекции;
неизвестный raw-элемент отклоняется при её создании. Укажите `unknownVariant: UnknownVariant::Error`
для явной ошибки неизвестного варианта или совместимый fallback-класс для его сохранения как DTO.

Default принадлежит декларации и не меняется с типом свойства. Для одиночного
объектного поля явно укажите `unknown: UnknownVariant::Error` или fallback-класс;
обычный KeepRaw даёт ошибку конфигурации в этой позиции. Ошибка называет позицию
и подсказывает допустимую политику. Skip не превращается в null.

Поле ниже принадлежит вашему DTO; ImageDto и RawDto — ваши модели:

```php
use ApiSutra\Attributes\DataTransfer\Shape;
use ApiSutra\Serialization\Shapes\VariantsShape;

#[Shape(new VariantsShape('type', ['image' => ImageDto::class], unknown: RawDto::class))]
public ImageDto|RawDto $attachment;
```

## Описать варианты один раз на типе <a id="type-variants"></a>

Объявите DtoVariants на интерфейсе, абстрактном или конкретном базовом классе.
Регистрация, контейнер и feature-флаг не нужны. Все классы map/fallback должны быть
конкретными и реализовывать/наследовать объявленный тип. Здесь default — Error:
KeepRaw и Skip не выполняют обещание вернуть объект указанного типа.

```php
use ApiSutra\Attributes\DataTransfer\DtoVariants;
use ApiSutra\Attributes\DataTransfer\Extras;
use ApiSutra\Serialization\Hydrator;

#[DtoVariants('type', ['text' => TextEvent::class], unknown: UnknownEvent::class)]
interface Event {}

final readonly class TextEvent implements Event
{
    public function __construct(public string $text) {}
}

final readonly class UnknownEvent implements Event
{
    public function __construct(#[Extras] public array $raw = []) {}
}

$dto = Hydrator::default()->hydrateJson('{"type":"new","payload":{"enabled":true}}', Event::class);
// UnknownEvent с полным PHP-представлением в raw.
```

Тот же Event работает в Returns (включая unwrap), native-полях `Event`/`?Event`,
DtoShape, Nested.type, ListShape(DtoShape(...)), pagination itemsType и continuation
finalType/awaitAs. Для native-интерфейса без декларации вариантов реализации не
угадываются; неоднозначный union DTO требует явной формы. Готовые объекты проверяются
через instanceof, включая элементы типизированной коллекции, без повторного выбора.

Для сторонних типов используйте существующий HydrationRules, не глобальный registry:

```php
use ApiSutra\Serialization\Rules\HydrationRules;

// Event, TextEvent и UnknownEvent — ваши модели без атрибута DtoVariants.
$rules = HydrationRules::create()->withVariants(
    Event::class,
    discriminator: 'type',
    map: ['text' => TextEvent::class],
    unknown: UnknownEvent::class,
);
```

Это альтернатива атрибуту, не дополнительная декларация для того же типа: совместное
применение даёт ошибку конфигурации. Определения принадлежат HydrationConfig и
изолированы между клиентами. Общая настройка JSON-формы по-прежнему равна true;
false отключает метаданные формы, но не выбор варианта и проверки PHP-типов.
Глобальной fallback-модели нет; локальные переключатели для включения вариантов не нужны.

### Выбор и границы расширений <a id="selection-boundaries"></a>

Класс выбирается один раз на входной узел, затем выполняется обычная конкретная
гидратация. Конкретный объявляющий класс может выбрать себя, в том числе как fallback.
Собственная DtoVariants выбранного класса не исполняется повторно на этом узле;
новый вложенный узел выбирает независимо. Прямой вызов подтипа не ищет декларацию
у родителей и реализуемых интерфейсов. Обычное наследование полей/профилей сохраняется.

Готовый объект handler/composite может соответствовать abstract-типу или интерфейсу
без DtoVariants. До HTTP проверяется наличие типа и его map, если она объявлена.
Для самой гидратации нужен конкретный класс или варианты; abstract-тип сам по себе
не является рецептом создания. Composite пропускает гидратацию только для объекта,
который уже соответствует объявленному типу ответа через instanceof. Остальные
объектные источники, включая stdClass и JsonSerializable, и массивы гидратируются.

Вся map и fallback проверяются без конструкторов, supports() и DI. Для Returns это
происходит до HTTP через общую проверку назначения; поля всех моделей, которыми
владеет custom-гидратор, заранее не компилируются. Native/custom выбирается после
конкретного класса; custom-гидратор получает именно его. Ошибки известной модели
никогда не превращаются в успешный fallback-объект.

Штатный выбор сохраняет исходную форму/путь. Casts, computed и заменяющие данные hooks
сохраняют [границу преобразования](scope.md); context->hydrate() принимает новые
PHP-данные. BeforeHydrate относится к объявленному типу ответа, AfterHydrate — к
конкретному runtime-типу; hooks родителей/интерфейсов повторно не вызываются.
Standalone hydrateJson не создаёт HTTP hooks и execution trace. Сериализация следует
правилам конкретного DTO, не добавляет discriminator и не восстанавливает Key-wrapper.

## Исполняемый пример HTTP, polling и webhook <a id="example"></a>

[Полный пример](../../../example/polymorphic-json/run.php) содержит исходный JSON,
две декларации вариантов, вложенные DTO, типизированный fallback с Extras, InputShape
перед cast словаря и четыре сценария неверной формы. Используется MockTransport,
без сети и Laravel:

```bash
php vendor/apisutra/php/docs/example/polymorphic-json/run.php
```

HTTP и webhook явно используют один HydrationConfig; standalone defaults не наследуют
неявно конфигурацию или Returns::hydrator другого клиента.
