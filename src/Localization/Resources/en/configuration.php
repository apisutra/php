<?php

declare(strict_types=1);

return [
    'configuration.allowlist_entries_must_contain_only_scheme_host_and_port' => 'Allowlist entries must contain only scheme, host and port',
    'configuration.clientconfig_authretryattempts_must_be_0' => 'ClientConfig.authRetryAttempts must be >= 0',
    'configuration.clientconfig_baseurl_must_not_be_empty' => 'ClientConfig.baseUrl must not be empty',
    'configuration.clientconfig_casts_class_not_found' => 'ClientConfig.casts[{type}] class {cast} not found',
    'configuration.clientconfig_casts_contains_an_invalid_value' => 'ClientConfig.casts[{type}] contains an invalid value',
    'configuration.clientconfig_casts_must_implement_hydrationcastinterface_or_serializationcastinterface' => 'ClientConfig.casts[{type}] {cast} must implement HydrationCastInterface or SerializationCastInterface',
    'configuration.clientconfig_connecttimeout_must_be_0' => 'ClientConfig.connectTimeout must be >= 0',
    'configuration.clientconfig_credentialsconfig_scopes_must_be_credentialsscopeconfig' => 'ClientConfig.credentialsConfig.scopes[{scope}] must be CredentialsScopeConfig',
    'configuration.clientconfig_defaultpollrequest_class_not_found' => 'ClientConfig.defaultPollRequest class not found: {pollRequestClass}',
    'configuration.clientconfig_defaultpollrequest_must_implement_requestinterface' => 'ClientConfig.defaultPollRequest must implement RequestInterface: {pollRequestClass}',
    'configuration.clientconfig_defaultpollrequest_must_not_be_empty' => 'ClientConfig.defaultPollRequest must not be empty',
    'configuration.clientconfig_delay_must_be_0' => 'ClientConfig.delay must be >= 0',
    'configuration.clientconfig_idempotencyheader_must_not_be_empty' => 'ClientConfig.idempotencyHeader must not be empty',
    'configuration.clientconfig_requestenrichers_must_implement_requestpartsenricherinterface' => 'ClientConfig.requestEnrichers[{index}] must implement RequestPartsEnricherInterface',
    'configuration.clientconfig_requestpartsenumoutput_cannot_be_object_for_query_header_path' => 'ClientConfig.requestPartsEnumOutput cannot be Object for query/header/path',
    'configuration.clientconfig_timeout_must_be_0' => 'ClientConfig.timeout must be >= 0',
    'configuration.contains_an_invalid_timezone' => '{path} contains an invalid timezone: {timezone}',
    'configuration.origin_policy_expects_a_list_of_origins' => 'Origin policy expects a list of origins',
    'configuration.ratelimitconfig_limit_must_be_positive' => 'RateLimitConfig::limit must be positive',
    'configuration.ratelimitconfig_period_must_be_positive_and_representable_in_microseconds' => 'RateLimitConfig::period must be positive and representable in microseconds for the default sleeper',
    'configuration.retryconfig_attempts_must_be_1' => 'RetryConfig.attempts must be >= 1',
    'configuration.retryconfig_basedelay_must_be_0' => 'RetryConfig.baseDelay must be >= 0',
    'configuration.retryconfig_maxdelay_must_be_basedelay' => 'RetryConfig.maxDelay must be >= baseDelay',
    'configuration.retryconfig_safemethods_must_contain_httpmethod_values' => 'RetryConfig.safeMethods must contain HttpMethod values',
    'configuration.retryconfig_totaltimeoutms_must_be_1' => 'RetryConfig.totalTimeoutMs must be >= 1',
    'configuration.shared_ratelimit_store_is_incompatible_with_ratelimitbackend_use_one' => 'Shared rateLimit.store is incompatible with rateLimitBackend; use one backend for quota accounting',
];
