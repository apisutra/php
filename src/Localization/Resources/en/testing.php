<?php

declare(strict_types=1);

return [
    'testing.fake_session_open' => 'A fake session already owns this client; change responses through that session.',
    'testing.fake_session_closed' => 'This fake session is closed.',
    'testing.unmocked_in_session' => 'No mock response was configured for {requestClass}.',
    'testing.transport_in_use' => 'Cannot replace the transport while SDK executions are active.',
    'testing.await_timeout_exceeded' => 'Await timeout exceeded',
    'testing.mock_transport_required' => 'An active MockTransport is required; call fake() or playback() first.',
    'testing.live_test_s_failed_status_s_code_s_message' => 'Live test "{operation}" failed: status={value1}, code={value2}, message={value3}',
    'testing.live_test_s_returned_an_unexpected_data_type_expected' => 'Live test "{operation}" returned an unexpected data type: expected={expectedClass}, actual={value2}',
    'testing.polling_timeout_details' => '{message} (timeout={timeout}s, interval={interval}ms), last: {last}',
];
