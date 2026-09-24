<?php

declare(strict_types=1);

return [
    'execution.cancelled' => 'Выполнение запроса отменено.',
    'execution.batch_request_execution_failed' => 'Ошибка выполнения batch запроса',
    'execution.dependsonexecutor_supports_only_sequential' => 'DependsOnExecutor поддерживает только Sequential',
    'execution.invalid_item_s_d_s_does_not_resolve_to' => 'Некорректный элемент {scope}[{index}]: {value2} не резолвится в RequestInterface',
    'execution.nested_request_client_must_support_contextualclientinterface_to_inherit_the' => 'Клиент вложенного запроса должен поддерживать ContextualClientInterface для наследования deadline',
    'execution.no_client_specified_for_batch_execution' => 'Не указан клиент для batch выполнения',
    'execution.pool_request_execution_failed' => 'Ошибка выполнения pool запроса',
    'execution.pool_consumption_failed' => 'Обработка pool прервана: {reason}.',
    'execution.pool_consumption_resolver_requires_count' => 'Для обработки pool с resolver конкурентности нужен массив или источник Countable. Для остальных iterable задайте конкурентность числом.',
    'execution.pool_resolver_invalid' => 'Resolver конкурентности обработки pool должен возвращать целое число и не бросать исключений.',
    'execution.unsupported_item_s_d_s' => 'Неподдерживаемый элемент {scope}[{index}]: {value2}',
];
