<?php

declare(strict_types=1);

return [
    'configuration.allowlist_entries_must_contain_only_scheme_host_and_port' => 'В allowlist требуется только схема, host и порт',
    'configuration.clientconfig_authretryattempts_must_be_0' => 'ClientConfig.authRetryAttempts должен быть >= 0',
    'configuration.clientconfig_baseurl_must_not_be_empty' => 'ClientConfig.baseUrl не должен быть пустым',
    'configuration.clientconfig_casts_class_not_found' => 'ClientConfig.casts[{type}] класс {cast} не найден',
    'configuration.clientconfig_casts_contains_an_invalid_value' => 'ClientConfig.casts[{type}] содержит недопустимое значение',
    'configuration.clientconfig_casts_must_implement_hydrationcastinterface_or_serializationcastinterface' => 'ClientConfig.casts[{type}] {cast} должен реализовывать HydrationCastInterface или SerializationCastInterface',
    'configuration.clientconfig_connecttimeout_must_be_0' => 'ClientConfig.connectTimeout должен быть >= 0',
    'configuration.clientconfig_credentialsconfig_scopes_must_be_credentialsscopeconfig' => 'ClientConfig.credentialsConfig.scopes[{scope}] должен быть CredentialsScopeConfig',
    'configuration.clientconfig_defaultpollrequest_class_not_found' => 'ClientConfig.defaultPollRequest класс не найден: {pollRequestClass}',
    'configuration.clientconfig_defaultpollrequest_must_implement_requestinterface' => 'ClientConfig.defaultPollRequest должен реализовывать RequestInterface: {pollRequestClass}',
    'configuration.clientconfig_defaultpollrequest_must_not_be_empty' => 'ClientConfig.defaultPollRequest не должен быть пустым',
    'configuration.clientconfig_delay_must_be_0' => 'ClientConfig.delay должен быть >= 0',
    'configuration.clientconfig_idempotencyheader_must_not_be_empty' => 'ClientConfig.idempotencyHeader не должен быть пустым',
    'configuration.clientconfig_requestenrichers_must_implement_requestpartsenricherinterface' => 'ClientConfig.requestEnrichers[{index}] должен реализовывать RequestPartsEnricherInterface',
    'configuration.clientconfig_requestpartsenumoutput_cannot_be_object_for_query_header_path' => 'ClientConfig.requestPartsEnumOutput не может быть Object для query/header/path',
    'configuration.clientconfig_timeout_must_be_0' => 'ClientConfig.timeout должен быть >= 0',
    'configuration.contains_an_invalid_timezone' => '{path} содержит недопустимую timezone: {timezone}',
    'configuration.origin_policy_expects_a_list_of_origins' => 'Origin policy ожидает список origin',
    'configuration.ratelimitconfig_limit_must_be_positive' => 'RateLimitConfig::limit должен быть положительным',
    'configuration.ratelimitconfig_period_must_be_positive_and_representable_in_microseconds' => 'RateLimitConfig::period должен быть положительным и представимым в микросекундах штатного sleeper',
    'configuration.retryconfig_attempts_must_be_1' => 'RetryConfig.attempts должен быть >= 1',
    'configuration.retryconfig_basedelay_must_be_0' => 'RetryConfig.baseDelay должен быть >= 0',
    'configuration.retryconfig_maxdelay_must_be_basedelay' => 'RetryConfig.maxDelay должен быть >= baseDelay',
    'configuration.retryconfig_safemethods_must_contain_httpmethod_values' => 'RetryConfig.safeMethods должен содержать значения HttpMethod',
    'configuration.retryconfig_totaltimeoutms_must_be_1' => 'RetryConfig.totalTimeoutMs должен быть >= 1',
    'configuration.shared_ratelimit_store_is_incompatible_with_ratelimitbackend_use_one' => 'Общий rateLimit.store несовместим с rateLimitBackend; перенесите учёт квот в единый backend',
];
