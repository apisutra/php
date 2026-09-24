<?php

declare(strict_types=1);

return [
    'execution.cancelled' => 'The SDK execution was cancelled.',
    'execution.batch_request_execution_failed' => 'Batch request execution failed',
    'execution.dependsonexecutor_supports_only_sequential' => 'DependsOnExecutor supports only Sequential',
    'execution.invalid_item_s_d_s_does_not_resolve_to' => 'Invalid item {scope}[{index}]: {value2} does not resolve to RequestInterface',
    'execution.nested_request_client_must_support_contextualclientinterface_to_inherit_the' => 'Nested request client must support ContextualClientInterface to inherit the deadline',
    'execution.no_client_specified_for_batch_execution' => 'No client specified for batch execution',
    'execution.pool_request_execution_failed' => 'Pool request execution failed',
    'execution.pool_consumption_failed' => 'Pool consumption stopped: {reason}.',
    'execution.pool_consumption_resolver_requires_count' => 'Pool consumption with a concurrency resolver requires an array or Countable source. Use an integer concurrency for other iterables.',
    'execution.pool_resolver_invalid' => 'The pool concurrency resolver must return an integer and must not throw.',
    'execution.unsupported_item_s_d_s' => 'Unsupported item {scope}[{index}]: {value2}',
];
