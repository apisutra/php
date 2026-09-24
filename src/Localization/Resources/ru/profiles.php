<?php

declare(strict_types=1);

return [
    'serialization.hydration_profile_class_not_found' => 'Класс профиля гидратации DTO не найден: {class}',
    'serialization.serialization_profile_class_not_found' => 'Класс профиля сериализации DTO не найден: {class}',
    'serialization.invalid_profile_cast' => 'Некорректный cast DTO ({direction}) для типа {type}',
    'serialization.hydration_profile_invalid' => 'Профиль гидратации DTO должен реализовывать DtoHydrationProfileInterface: {class}',
    'serialization.serialization_profile_invalid' => 'Профиль сериализации DTO должен реализовывать DtoSerializationProfileInterface: {class}',
    'serialization.profile_conflict' => 'Конфликт профилей DTO для {class}: {bound} != {configured}',
    'serialization.public_properties_only' => 'DTO serializer поддерживает только публичные data-свойства: {class}::${property}',
    'serialization.property_already_initialized' => 'Свойство DTO уже инициализировано до fallback-присваивания: {class}::${property}',
];
