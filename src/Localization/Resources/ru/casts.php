<?php

declare(strict_types=1);

return [
    'casts.cast_must_implement_hydrationcastinterface_or_serializationcastinterface' => 'Каст {cast} должен реализовывать HydrationCastInterface или SerializationCastInterface',
    'casts.datauribase64filecast_hydrate_expects_string_null' => 'DataUriBase64FileCast::hydrate ожидает string|null',
    'casts.datauribase64filecast_received_an_invalid_base64_payload' => 'DataUriBase64FileCast получил невалидный base64 payload',
    'casts.datauribase64filecast_serialize_expects_base64file_string_null' => 'DataUriBase64FileCast::serialize ожидает Base64File|string|null',
    'casts.datetimecast_serialize_expects_datetimeinterface' => 'DateTimeCast::serialize ожидает DateTimeInterface',
    'casts.enumcast_hydrate_requires_a_backed_enum' => 'EnumCast::hydrate требует backed enum',
    'casts.integercast_value_is_outside_the_int_range_during_serialization' => 'Число вне диапазона int при сериализации IntegerCast',
    'casts.invalid_timezone_in_datetimecast_configuration' => 'Некорректная timezone в конфигурации DateTimeCast',
    'casts.text_booleancast_expects_bool_or_null' => 'Текстовый BooleanCast ожидает bool или null',
];
