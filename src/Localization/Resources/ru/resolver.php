<?php

declare(strict_types=1);

return [
    'configuration.request_namespaces_missing' => 'Не найдены namespace запросов клиента \'{class}\'',
    'resolver.expected_clientinterface_in_the_services_list' => 'Ожидается ClientInterface в списке сервисов',
    'resolver.namespace_is_already_registered_for_a_client' => 'Namespace \'{namespace}\' уже зарегистрирован для клиента',
    'resolver.no_client_registered_for_request' => 'Клиент для запроса \'{requestClass}\' не зарегистрирован',
    'resolver.no_requests_found_for_client' => 'Не найдены запросы клиента \'{clientClass}\'',
    'resolver.request_belongs_to_client_not' => 'Запрос \'{requestClass}\' принадлежит клиенту \'{expectedClass}\', а не \'{clientClass}\'',
];
