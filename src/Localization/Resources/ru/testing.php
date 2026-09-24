<?php

declare(strict_types=1);

return [
    'testing.fake_session_open' => 'Клиентом уже владеет сессия fake; меняйте ответы через неё.',
    'testing.fake_session_closed' => 'Эта сессия fake закрыта.',
    'testing.unmocked_in_session' => 'Не настроен mock-ответ для {requestClass}.',
    'testing.transport_in_use' => 'Нельзя заменить транспорт при активных исполнениях SDK.',
    'testing.await_timeout_exceeded' => 'Превышен таймаут ожидания',
    'testing.mock_transport_required' => 'Требуется активный MockTransport; сначала вызовите fake() или playback().',
    'testing.live_test_s_failed_status_s_code_s_message' => 'Live-тест "{operation}" завершился ошибкой: status={value1}, code={value2}, message={value3}',
    'testing.live_test_s_returned_an_unexpected_data_type_expected' => 'Live-тест "{operation}" вернул неожиданный тип данных: expected={expectedClass}, actual={value2}',
    'testing.polling_timeout_details' => '{message} (таймаут={timeout}с, интервал={interval}мс), последнее значение: {last}',
];
