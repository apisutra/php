<?php

declare(strict_types=1);

return [
    'result.mismatch_message_must_not_be_empty' => 'mismatchMessage must be a non-empty string or null',
    'result.dto_class_is_unavailable' => 'Result DTO class is unavailable: {expected}',
    'result.response_type_mismatch' => 'Invalid result of {request}: expected {expected}, got {actual}',
    'result.exception_factory_failed' => 'Failed to create the SDK exception',
    'result.continuation_orchestration_requires_a_resulthandle_client_context' => 'Continuation orchestration requires a ResultHandle client context',
    'result.continuation_token_is_missing_from_the_result' => 'Continuation token is missing from the result',
    'result.finaltype_must_not_be_empty' => 'finalType must not be empty',
    'result.invalid_promise_result_type' => 'Invalid promise result type',
    'result.multiple_errors' => 'Multiple errors: {count}. {value1}',
    'result.request_execution_failed' => 'Request execution failed',
];
