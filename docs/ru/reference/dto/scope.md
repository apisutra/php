<!-- languages --> <a href="../../../en/reference/dto/scope.md">English</a> · <a href="scope.md">Русский</a> <!-- /languages -->
# Контексты преобразования <a id="section-1"></a>

Обработчик получает обязательный контекст своего направления. Он действует одинаково
для атрибута, профиля, внешнего HandlerSpec и поэлементного cast. Для вложенных DTO
используйте контекст: он сохраняет действующие правила и текущую ветку обхода.

## Cast и provider <a id="section-2"></a>

| Интерфейс | Метод |
| --- | --- |
| HydrationCastInterface | `hydrate(mixed $value, HydrationContext $context): mixed` |
| SerializationCastInterface | `serialize(mixed $value, SerializationContext $context): mixed` |
| CastInterface | Наследует оба направленных интерфейса |
| DefaultValueProviderInterface | `resolve(mixed $value, ValueState $state, array $source, HydrationContext $context): mixed` |

Интерфейсы cast находятся в `ApiSutra\Contracts\Interfaces\Casting`, provider —
в `Contracts\Interfaces\DataTransfer`, контексты — в `ApiSutra\Serialization\Context`.
`source` содержит исходные данные DTO после unwrap/computed; значения
[ValueState и порядок defaults](defaults.md) сохраняются.

Полный [EntryCast](../../../example/hydration-rules/src/EntryCast.php) вызывает
`$context->hydrate($value, EntryDto::class)` и выполняется
[поставляемым примером](../../examples/hydration-rules.md).

`HandlerSpec($class, $args)` создаёт обработчик на каждое применение поля или элемента.
Property Cast создаётся на поле, атрибутный Nested.itemCast — один раз на проход списка.
Профильные и явно переданные экземпляры сохраняют свой жизненный цикл. Контекст
создаётся отдельно перед каждым вызовом обработчика, включая встроенные casts.

## Вложенные преобразования <a id="section-3"></a>

| Контекст | Метод | Поведение |
| --- | --- | --- |
| HydrationContext | `hydrate(array\|object $data, string $class): object` | Текущие правила, возвращаемый тип соответствует классу DTO |
| HydrationContext | `hydrateCollection(array $items, string $class): array` | Список DTO с теми же правилами |
| SerializationContext | `serialize(object $dto): array` | Текущая ветка сериализации и исключение receiver |
| HydrationContext | `http(): ?HttpMappingContext` | То же HTTP-расширение или null вне HTTP |
| Оба | `extension(string $type): ?object` | Расширение по точному имени класса либо null |

При вложенной гидратации источник считается преобразованным обработчиком:
[диагностика](diagnostics.md) сохраняет Boundary, не придумывая точный sourcePath.
В DX-сериализации дочерний DTO разрешает собственный профиль. В wire/serializeWithPolicy
дочерний DTO наследует явно выбранные policy и registry. Циклы проверяются в текущей
ветке; повторная ссылка в соседних элементах допустима.

Cast над видимым receiver запрещён до вызова обработчика. Созданный
внутри обработчика DTO можно сериализовать через контекст; применяются обычные
[правила receiver](../serialization/receiver-output.md).

## Время жизни <a id="section-4"></a>

После возврата или исключения обработчика **любой метод его контекста** выбрасывает
ConfigurationException. Контекст освобождает ссылки на состояние и HTTP-расширения.
Не сохраняйте его в singleton, registry или DTO для последующих операций. Вложенный
обработчик получает собственный контекст, пока внешний продолжает действовать.
Конкурентное использование одного контекста из разных Fiber не поддерживается.

Прямой вызов cast без контекста не поддерживается. Для самостоятельного преобразования
используйте Hydrator/DtoSerializer и декларацию обработчика. Новый вызов
Hydrator::default() внутри handler не наследует правила текущего клиента.

## HTTP-данные <a id="section-5"></a>

`$context->extension(HttpMappingContext::class)` возвращает снимок ссылок текущего
вызова. Класс находится в `ApiSutra\Serialization\Integration`.

| Поле readonly | Тип / назначение |
| --- | --- |
| traceId | string идентификатор вызова |
| request | RequestInterface, исходный экземпляр запроса |
| response | ?ProviderResponse, текущий ответ без fallback на lastResponse |
| role | RequestRole |
| options | ?RequestOptions |
| paginationOptions | ?PaginationOptions |

Снимок не читает и не клонирует streams. Readonly оболочка сохраняет идентичность
вложенных объектов, не делает их неизменяемыми. При сериализации HTTP-запроса response
обычно null. Standalone и гидратация сохранённого await outcome не создают HTTP extension.

PipelineContext и ClientConfig через extension недоступны. Стабильные настройки
передавайте зависимостями обработчика; управление retry, budget, parent и изменение
пайплайна выполняйте в [hooks](../extensions/hooks.md) или middleware.

Пользовательские [гидраторы DTO](hydrators.md) тоже получают HydrationContext. `$context->http()` возвращает то же расширение; extension() сохраняется. Выбранный гидратор наследуется вложенными преобразованиями.
