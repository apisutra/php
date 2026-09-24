<?php

declare(strict_types=1);

return [
    'serialization.hydration_profile_class_not_found' => 'DTO hydration profile class not found: {class}',
    'serialization.serialization_profile_class_not_found' => 'DTO serialization profile class not found: {class}',
    'serialization.invalid_profile_cast' => 'Invalid DTO {direction} cast for type {type}',
    'serialization.hydration_profile_invalid' => 'DTO hydration profile must implement DtoHydrationProfileInterface: {class}',
    'serialization.serialization_profile_invalid' => 'DTO serialization profile must implement DtoSerializationProfileInterface: {class}',
    'serialization.profile_conflict' => 'DTO profile conflict for {class}: {bound} != {configured}',
    'serialization.public_properties_only' => 'DTO serializer supports only public data properties: {class}::${property}',
    'serialization.property_already_initialized' => 'DTO hydration property already initialized before fallback assignment: {class}::${property}',
];
