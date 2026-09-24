<!-- languages --> <a href="../../en/development/hydration.md">English</a> · <a href="hydration.md">Русский</a> <!-- /languages -->
# Исполнение входной гидратации <a id="section-1"></a>

Hydrator связывает описание модели с входным планом, обрабатывает свойства и создаёт
DTO. Атрибуты и внешние правила используют одного исполнителя. Пользовательские
контракты находятся в [справочнике DTO](../reference/dto/README.md); этот документ
описывает внутренние границы.

## Владельцы решений <a id="section-2"></a>

| Компонент | Ответственность |
| --- | --- |
| [MetadataCatalog](../../../src/Metadata/MetadataCatalog.php) | Нейтральные Reflection-сведения о классе и области объявления свойства |
| [RuleSetCompiler](../../../src/Serialization/Rules/RuleSetCompiler.php) | Конфликты, применимые правила, политики и receiver; публикация проверенного графа описаний |
| [HydrationPlanCompiler](../../../src/Serialization/Hydration/HydrationPlanCompiler.php) | Источник/fallback, presence, нормализация, defaults, операция преобразования и constructor slots |
| [HydrationPlan](../../../src/Serialization/Hydration/HydrationPlan.php) | Поля и рецепты аргументов; bind создаёт значения атрибутов текущего узла |
| [HydrationFieldExecutor](../../../src/Serialization/Hydration/HydrationFieldExecutor.php) | Исполнение стадий поля, consumption, source location и итоговая проверка |
| [HydrationObjectFactory](../../../src/Serialization/Hydration/HydrationObjectFactory.php) | Аргументы конструктора, один constructor, constructorValue и назначение остальных полей |
| [HydrationScope](../../../src/Serialization/Rules/HydrationScope.php) | Активный обход, пути и временный контекст обработчиков |

HydrationFieldPlan выбирает Cast, Shape, Identity, Nested или Builtin. Это внутренний
замкнутый набор операций, без регистрации пользовательских стадий. BuiltinHydrationCaster
и HydrationTypeSelector выбирают преобразование native-типа по текущему значению;
конкретная ветвь union не замораживается при компиляции.

## План и текущий вызов <a id="section-3"></a>

Ключ `:hydration-plan` в AttributeMetadataCache хранит планы в WeakMap по объекту
CompiledDtoRules. Поэтому одинаковый класс при разных правилах получает разные планы
даже с общим кешем. Сброс описаний или освобождение гидратора освобождает зависимые планы;
каталог структуры не получает политику клиента.

План не содержит DTO, входных данных, handler instances или результатов профиля.
Безопасные значения деклараций переиспользуются. Атрибут с объектными аргументами
остаётся Reflection-рецептом, который выполняется для каждого узла, включая
пропущенные/null поля. После bind разрешается живой профиль DTO; его policy и registry
действуют в текущем узле. При выключенном metadata cache план строится заново,
но проверенные описания правил сохраняют собственную memoization.

План публикуется после успешной материализации всех атрибутов узла. Ошибка рецепта
не публикует половину плана и не удерживает созданные аргументы. Ошибка при повторном
bind не портит готовый рецепт; следующий вызов повторяет материализацию.
[Жизненный цикл значений](../reference/dto/lifecycle.md) одинаков с кешем и без него.

## Порядок стадий <a id="section-4"></a>

1. Проверить доступность DTO и разрешить его декларации.
2. Нормализовать вход; вызвать computed() у Response DTO.
3. Связать план и материализовать аргументы атрибутов в порядке свойств.
4. Разрешить профиль. Для каждого поля выбрать primary/fallback и определить
   Missing/Null/Present, затем проверить required, forbidExplicitNull и входную форму.
5. Нормализовать пустую строку, применить подходящий default/provider, выполнить
   выбранную операцию и итоговые scalar/native проверки.
6. Собрать consumption/extras и аргументы, вызвать конструктор один раз, проверить
   constructorValue и заполнить остальные свойства.

Missing и null различаются до defaults. Provider и пользовательский cast отмечают
границу происхождения; paths/candidates и безопасный контекст ошибки сохраняются.
Объекты из default конструктора создаёт PHP только при пропущенном аргументе.

## Разные контракты вложенности <a id="section-5"></a>

RuleValueProcessor исполняет ValueShape; NestedValueProcessor сохраняет происхождение
атрибутного списка при включённых возможностях правил. LegacyNestedHydrator исполняет
прежнюю операцию Nested без замены её строгой list-формой. Их различия в ключах,
вариантах, skip/error, itemCast и потреблении источника намеренны.

Новые правила преобразования добавляют в профильный обработчик. В Hydrator остаются
корневая область, нормализация источника и сборка фаз. В ObjectFactory не добавляют
повторное преобразование входа или запись в уже инициализированное readonly-поле.
