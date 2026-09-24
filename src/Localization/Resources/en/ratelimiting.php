<?php

declare(strict_types=1);

return [
    'ratelimiting.invalid_redis_io_timeout' => 'Redis ioTimeoutMs must be positive and representable.',
    'ratelimiting.active_quota_definition_changed' => 'Active quota definition changed',
    'ratelimiting.active_redis_quota_definition_changed' => 'Active Redis quota definition changed',
    'ratelimiting.conflicting_definitions_of_the_same_quota' => 'Conflicting definitions of the same quota',
    'ratelimiting.failed_to_set_a_finite_redis_read_timeout' => 'Failed to set a finite Redis read timeout',
    'ratelimiting.invalid_blocking_quota_identifier' => 'Invalid blocking quota identifier',
    'ratelimiting.invalid_blocking_quota_list' => 'Invalid blocking quota list',
    'ratelimiting.invalid_quota_a_key_positive_limit_and_representable_periodms' => 'Invalid quota: a key, positive limit and representable periodMs are required',
    'ratelimiting.invalid_quota_accounting_result' => 'Invalid quota accounting result',
    'ratelimiting.phpredis_backend_required' => 'The Redis backend requires phpredis >= 6.2 and a Redis object',
    'ratelimiting.provide_an_open_dedicated_redis_connection' => 'Provide an open dedicated Redis connection',
    'ratelimiting.quota_behavior_is_not_specified' => 'Quota behavior is not specified',
    'ratelimiting.quota_value_is_outside_the_exact_integer_range_of' => 'Quota value is outside the exact integer range of Redis Lua',
    'ratelimiting.quota_window_end_cannot_be_represented_in_milliseconds' => 'Quota window end cannot be represented in milliseconds',
    'ratelimiting.rate_limit_exceeded' => 'Rate limit exceeded',
    'ratelimiting.rate_limit_lua_resource_not_found' => 'Rate limit Lua resource not found',
    'ratelimiting.rate_limit_wait_must_be_representable_in_microseconds_for' => 'Rate limit wait must be representable in microseconds for the default sleeper',
    'ratelimiting.ratelimitconfig_period_must_allow_computing_the_window_end_without' => 'RateLimitConfig::period must allow computing the window end without overflow',
    'ratelimiting.redis_quota_window_end_cannot_be_represented_exactly' => 'Redis quota window end cannot be represented exactly',
    'ratelimiting.redis_rate_limit_requires_a_non_empty_scope_and' => 'Redis rate limit requires a non-empty scope and a positive representable ioTimeoutMs',
    'ratelimiting.redis_backend_requires_normal_mode' => 'The Redis backend requires normal mode, no serializer/compression and no automatic retries',
];
