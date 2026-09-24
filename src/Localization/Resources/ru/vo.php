<?php

declare(strict_types=1);

return [
    'vo.a_compatible_validation_factory_is_unavailable_for_validate' => 'Для #[Validate] недоступна совместимая фабрика валидации. {instruction}',
    'vo.configure_validatorfactory_in_the_selected_containerprovider' => 'Настройте validatorFactory() в выбранном containerProvider.',
    'vo.connect_illuminate_validation_through_a_container_provider_or_validator' => 'Подключите Illuminate Validation через container provider или Validator::useFactory().',
    'vo.failed_to_decode_response_json' => 'Не удалось разобрать JSON ответа: {value0}',
    'vo.failed_to_open_file' => 'Не удалось открыть файл: {path}',
    'vo.failed_to_resolve_the_validator_for_validate' => 'Не удалось получить валидатор для #[Validate]. {instruction}',
    'vo.failed_to_save_file' => 'Не удалось сохранить файл: {path}',
    'vo.http_body_cannot_contain_both_a_string_and_a' => 'HTTP-тело не может одновременно содержать строку и поток',
    'vo.http_response_must_contain_a_string_or_a_stream' => 'HTTP-ответ должен содержать строку либо поток',
    'vo.response_is_not_an_archive' => 'Ответ не является архивом',
    'vo.streaming_response_must_be_read_explicitly_through_stream' => 'Потоковый ответ нужно читать явно через stream',
    'vo.transport_timeouts_must_be_0' => 'Транспортные таймауты должны быть >= 0',
];
