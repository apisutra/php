<!-- languages --> <a href="../../en/glossary/dto.md">English</a> · <a href="dto.md">Русский</a> <!-- /languages -->
# DTO <a id="section-1"></a>

| Термин | Значение | Подробнее |
| --- | --- | --- |
| <a id="dtointerface"></a> DtoInterface | Интерфейс DTO ApiSutra с фабрикой `from(array\|object): static`. | [Контракт](../reference/dto/models.md) |
| <a id="responsedtointerface"></a> ResponseDtoInterface | Интерфейс для корневых Response DTO. | [Контракт](../reference/dto/models.md) |
| <a id="abstractdto"></a> AbstractDto | Необязательный базовый класс DTO. | [Контракт](../reference/dto/models.md) |
| <a id="dtohydrationprofileinterface"></a> DtoHydrationProfileInterface | Контракт именования и преобразований входной модели, подключаемый через атрибут класса или общей базы. | [Контракт](../reference/dto/profiles.md) |
| <a id="dtohydrationpolicy"></a> DtoHydrationPolicy | Immutable policy DTO hydration после резолва defaults/override. | [Контракт](../reference/dto/profiles.md) |
| <a id="dtohydrationprofile-attribute"></a> DtoHydrationProfile (атрибут) | Class-level binding атрибут для привязки DTO или базового DTO к `DtoHydrationProfileInterface`. | [Контракт](../reference/dto/profiles.md) |
| <a id="dtohydrate-attribute"></a> DtoHydrate (атрибут) | Атрибут частичной настройки входной модели поверх профиля. | [Контракт](../reference/dto/profiles.md) |
| <a id="dtoserializationprofileinterface"></a> DtoSerializationProfileInterface | Контракт централизованной body DTO policy для SDK/пакета. | [Контракт](../reference/serialization/dto-output.md) |
| <a id="dtoserializationpolicy"></a> DtoSerializationPolicy | Immutable policy DTO-сериализации после резолва defaults/override. | [Контракт](../reference/serialization/dto-output.md) |
| <a id="dtoserializationprofile-attribute"></a> DtoSerializationProfile (атрибут) | Class-level binding атрибут для привязки DTO или базового DTO к `DtoSerializationProfileInterface`. | [Контракт](../reference/serialization/dto-output.md) |
| <a id="dtoserialize-attribute"></a> DtoSerialize (атрибут) | Class-level partial override поверх package/profile defaults. | [Контракт](../reference/serialization/dto-output.md) |
| <a id="abstractresponsedto"></a> AbstractResponseDto | Абстрактный класс для корневых Response DTO. | [Контракт](../reference/dto/models.md) |
| <a id="validatableinterface"></a> ValidatableInterface | Интерфейс для объектов с валидацией. | [Контракт](../reference/client/validation.md) |
| <a id="customvalidatablerequestinterface"></a> CustomValidatableRequestInterface | Расширяемая preflight-валидация запроса до сериализации/HTTP. | [Контракт](../reference/client/validation.md) |
| <a id="validatorinterface"></a> ValidatorInterface | Контракт валидатора. | [Контракт](../reference/client/validation.md) |
| <a id="validationresult"></a> ValidationResult | Результат валидации. | [Контракт](../reference/client/validation.md) |
| <a id="nested-attribute"></a> Nested (атрибут) | Атрибут для гидратации одиночного объекта или списка, включая проекции и полиморфные элементы. | [Контракт](../reference/dto/shapes.md) |
| <a id="from-attribute"></a> From (атрибут) | Атрибут для переименования поля при гидрации DTO. | [Контракт](../reference/dto/profiles.md) |
| <a id="to-attribute"></a> To (атрибут) | Атрибут для сериализации DTO в body запроса. | [Контракт](../reference/serialization/dto-output.md) |
| <a id="defaultvalue-attribute"></a> DefaultValue (атрибут) | Значение или provider для Missing/Null/Present согласно when. | [Контракт](../reference/dto/defaults.md) |
| <a id="defaultvalueproviderinterface"></a> DefaultValueProviderInterface | Контракт для вычисления сложных дефолтов. | [Контракт](../reference/dto/defaults.md) |
| <a id="valuestate-enum"></a> ValueState (enum) | Состояние значения при извлечении из ответа: Missing, Null, Present. | [Контракт](../reference/dto/defaults.md) |
| <a id="cast-attribute"></a> Cast (атрибут) | Атрибут для кастомного преобразования значения при гидрации. | [Контракт](../reference/serialization/casts.md) |
| <a id="datetimefrom-attribute"></a> DateTimeFrom (атрибут) | Property-level override date-time hydration semantics. | [Контракт](../reference/dto/profiles.md) |
| <a id="datetimeto-attribute"></a> DateTimeTo (атрибут) | Property-level override date-time body serialization semantics. | [Контракт](../reference/serialization/dto-output.md) |
| <a id="emptystringasnull-attribute"></a> EmptyStringAsNull (атрибут) | Property-level hydration normalizer. | [Контракт](../reference/dto/defaults.md) |
| <a id="castinterface"></a> CastInterface | Интерфейс преобразователя с методами `hydrate()` и `serialize()`. | [Контракт](../reference/serialization/casts.md) |
| <a id="castregistry"></a> CastRegistry | Реестр casts для сериализации; гидратация его не читает. | [Контракт](../reference/serialization/casts.md) |
| <a id="hydrator"></a> Hydrator | Преобразует PHP-массивы в DTO; forRules() явно подключает внешний набор. | [Контракт](../reference/dto/lifecycle.md) |
| <a id="dtoserializer"></a> DtoSerializer | Сериализатор DTO; контекст клиента может добавить wire-policy и исключение receiver. | [Контракт](../reference/serialization/dto-output.md) |
| <a id="computed"></a> computed() | Завершающий обработчик Response DTO после заполнения входных свойств. | [Контракт](../reference/dto/lifecycle.md) |
| <a id="hydrationrules"></a> HydrationRules | Неизменяемый набор правил классов и общих defaults, привязанный к клиенту или standalone-гидратору. | [Контракт](../reference/dto/field-rules.md) |
| <a id="dtorules"></a> DtoRules | Правила конкретного класса: policy, поля и необязательный receiver. | [Контракт](../reference/dto/field-rules.md) |
| <a id="fieldrule"></a> FieldRule | Правило поля: mapping, присутствие, default и выбранное преобразование. | [Контракт](../reference/dto/field-rules.md) |
| <a id="valueshape"></a> ValueShape | Описание скаляра, DTO, списка или вариантов элементов с проверкой вложенной формы. | [Контракт](../reference/dto/shapes.md) |
| <a id="rulepolicy"></a> RulePolicy | Частичная policy скаляров, пустых строк, имён, дат и casts. | [Контракт](../reference/dto/field-rules.md) |
| <a id="scalarpolicy"></a> ScalarPolicy | Режим Legacy либо Strict проверки скалярных значений. | [Контракт](../reference/dto/scalars.md) |
| <a id="scalartype"></a> ScalarType | Обозначение скалярной ветки ValueShape. | [Контракт](../reference/dto/scalars.md) |
| <a id="inputshape"></a> InputShape | Предусловие Object либо List для исходного значения поля. | [Контракт](../reference/dto/shapes.md) |
| <a id="handlerspec"></a> HandlerSpec | Описание класса обработчика и аргументов из допустимых значений. | [Контракт](../reference/dto/field-rules.md) |
| <a id="defaultspec"></a> DefaultSpec | Описание literal default либо provider для выбранных состояний значения. | [Контракт](../reference/dto/field-rules.md) |
| <a id="sourcepathkind"></a> SourcePathKind | Степень точности пути к исходным данным в ошибке гидратации. | [Контракт](../reference/dto/diagnostics.md) |

[Все термины](README.md).
| <a id="hydrationcontext"></a> HydrationContext | Контекст одного входного обработчика; сохраняет текущие правила вложенных DTO. | [Контракт](../reference/dto/scope.md) |
| <a id="serializationcontext"></a> SerializationContext | Контекст одного выходного обработчика; сохраняет policy и ветку обхода. | [Контракт](../reference/dto/scope.md) |
