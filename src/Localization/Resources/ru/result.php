<?php

declare(strict_types=1);

return [
    'result.mismatch_message_must_not_be_empty' => 'mismatchMessage должен быть непустой строкой или null',
    'result.dto_class_is_unavailable' => 'Недоступен класс DTO результата: {expected}',
    'result.response_type_mismatch' => 'Некорректный результат {request}: ожидается {expected}, получено {actual}',
    'result.exception_factory_failed' => 'Не удалось сформировать исключение SDK',
    'result.continuation_orchestration_requires_a_resulthandle_client_context' => 'Continuation orchestration недоступен без client контекста ResultHandle',
    'result.continuation_token_is_missing_from_the_result' => 'Continuation token отсутствует в результате',
    'result.finaltype_must_not_be_empty' => 'finalType не должен быть пустым',
    'result.invalid_promise_result_type' => 'Некорректный тип результата промиса',
    'result.multiple_errors' => 'Несколько ошибок: {count}. {value1}',
    'result.request_execution_failed' => 'Ошибка выполнения запроса',
];
