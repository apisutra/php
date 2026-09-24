<!-- languages --> <a href="../../../en/reference/dto/collections.md">English</a> · <a href="collections.md">Русский</a> <!-- /languages -->
# Типизированные коллекции <a id="section-1"></a>

Этот гайд описывает typed‑коллекции в ApiSutra и их интеграцию с DTO.

## Зачем <a id="section-2"></a>
Typed‑коллекции дают предсказуемый контракт:
- IDE и статанализ знают точный тип элементов
- меньше ошибок при работе с ответами
- удобные методы `first()/count()/isEmpty()`

## AbstractTypedCollection <a id="section-3"></a>
Базовый typed‑контейнер. Требует указать класс элемента и делает runtime‑guard.

```php
use ApiSutra\Collections\AbstractTypedCollection;

final readonly class OrderItemCollection extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return OrderItemDto::class;
    }
}
```

### Доступные методы <a id="section-4"></a>
- `all(): array<int|string, T>`
- `first(callback|null, default|null): ?T|mixed`
- `get(key, default?): T|mixed`
- `has(keys): bool`, `hasAny(keys): bool`
- `only(keys): static`, `except(keys): static`
- `count(): int`, `isEmpty(): bool`
- `map(callable(T): T): static`
- `filter(callback|null): static` (без callback удаляет falsy)
- `contains(value|callback|key, operator, value): bool`
- `firstWhere(key, operator?, value?): ?T`
- `pluck(value, key?): RawCollection`
- `keyBy(key|callable): static`
- `sortBy(key|callable, options?, desc=false): static`
- `sortByDesc(key|callable, options?): static`
- `unique(key|callable|null, strict=false): static`
- `values(): static`
- `mapToArray(callable(T): mixed): array`
- `fromArray(array $items): static`
- `toArray(): array`

### Guard <a id="section-5"></a>
В конструкторе проверяется, что все элементы — `instanceof itemClass()`.
При нарушении guard выбрасывается `ConfigurationException`.

### Общая коллекция для похожих DTO <a id="section-6"></a>
Если есть несколько очень похожих DTO (например, записи прав и обременений),
и хочется общий класс коллекции — используйте **общую абстракцию**:
интерфейс или базовый DTO.

```php
interface RightRecordInterface {}

final readonly class RegistrationRecordDto implements RightRecordInterface {}
final readonly class EncumbranceRecordDto implements RightRecordInterface {}

final readonly class RightRecordCollection extends AbstractTypedCollection
{
    protected static function itemClass(): string
    {
        return RightRecordInterface::class;
    }
}
```

Когда применять:
- есть общий контракт и поведение для элементов;
- нужен один тип коллекции в разных местах;
- важна типобезопасность и guard.

Когда **не** применять:
- элементы не имеют общего контракта;
- нужны “контекстные” типы (тогда лучше `RawCollection`).

## Интеграция с DTO <a id="section-7"></a>
Чтобы DTO получал коллекцию вместо массива, используйте `#[Nested]`:

```php
use ApiSutra\Attributes\DataTransfer\Nested;
use ApiSutra\DataTransfer\AbstractResponseDto;

final readonly class OrderDto extends AbstractResponseDto
{
    public function __construct(
        #[Nested(type: OrderItemDto::class)]
        public OrderItemCollection $items,
    ) {}
}
```

## RawCollection (escape‑hatch) <a id="section-8"></a>
Если нужен контейнер без типовой проверки — используйте `RawCollection`.
Он поддерживает те же базовые методы, но не выполняет guard.

Для полиморфных массивов (`#[Nested(discriminator: ..., map: ...)]`) правила такие же,
как для обычного `array`: работают `discriminatorMode` (`Value`/`Key`) и
`unknownVariant` (`KeepRaw`/`Skip`/`Error`).

## Сериализация <a id="section-9"></a>
`toArray()` делает глубокую сериализацию:
- DTO → `toArray()`
- `JsonSerializable` → `jsonSerialize()`
- остальное → как есть

## Приоритеты <a id="section-10"></a>
Typed‑коллекции не влияют на `#[Cast]` и registry‑касты.
Они лишь задают тип контейнера для списков.

## Ключи и индексация <a id="section-11"></a>
`map()` и `filter()` **сохраняют ключи**, как в Laravel.
Если нужна переиндексация — используйте `values()`.

## Автоматическая пустая коллекция <a id="section-12"></a>

Для **non-nullable typed collection** ядро автоматически подставляет пустую коллекцию,
если поле **отсутствует** (`ValueState::Missing`).

Это правило:
- работает только для `AbstractTypedCollection`
- работает только для **missing**
- не срабатывает для `null`
- не срабатывает для nullable-свойства (`?OrderItemCollection`)
- не перебивает `#[DefaultValue]`, если `DefaultValue` сам покрывает `Missing`

Если вам нужно поведение `null -> []`, оставляйте явный
`#[DefaultValue(value: [], when: [ValueState::Null])]`
или комбинированный `Missing + Null`.

Если `DefaultValue` покрывает только `Null`, built-in fallback для `Missing`
продолжит работать.
