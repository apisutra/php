<?php

declare(strict_types=1);

return [
    'ratelimiting.invalid_redis_io_timeout' => 'Redis ioTimeoutMs должен быть положительным и представимым.',
    'ratelimiting.active_quota_definition_changed' => 'Определение действующей квоты изменилось',
    'ratelimiting.active_redis_quota_definition_changed' => 'Определение действующей Redis-квоты изменилось',
    'ratelimiting.conflicting_definitions_of_the_same_quota' => 'Противоречивые определения одной квоты',
    'ratelimiting.failed_to_set_a_finite_redis_read_timeout' => 'Не удалось задать конечный read timeout Redis',
    'ratelimiting.invalid_blocking_quota_identifier' => 'Некорректный идентификатор блокирующей квоты',
    'ratelimiting.invalid_blocking_quota_list' => 'Некорректный список блокирующих квот',
    'ratelimiting.invalid_quota_a_key_positive_limit_and_representable_periodms' => 'Некорректная квота: требуются ключ, положительный limit и представимый periodMs',
    'ratelimiting.invalid_quota_accounting_result' => 'Некорректный результат учёта квот',
    'ratelimiting.phpredis_backend_required' => 'Для Redis backend требуется расширение phpredis >= 6.2 и объект Redis',
    'ratelimiting.provide_an_open_dedicated_redis_connection' => 'Передайте открытое выделенное Redis-соединение',
    'ratelimiting.quota_behavior_is_not_specified' => 'Не задано поведение квоты',
    'ratelimiting.quota_value_is_outside_the_exact_integer_range_of' => 'Значение квоты выходит за точный целочисленный диапазон Redis Lua',
    'ratelimiting.quota_window_end_cannot_be_represented_in_milliseconds' => 'Конец окна квоты не представим в миллисекундах',
    'ratelimiting.rate_limit_exceeded' => 'Превышен лимит запросов',
    'ratelimiting.rate_limit_lua_resource_not_found' => 'Не найден Lua resource rate-limit',
    'ratelimiting.rate_limit_wait_must_be_representable_in_microseconds_for' => 'Ожидание rate-limit должно быть представимым в микросекундах штатного sleeper',
    'ratelimiting.ratelimitconfig_period_must_allow_computing_the_window_end_without' => 'RateLimitConfig::period должен позволять вычислить конец окна без переполнения',
    'ratelimiting.redis_quota_window_end_cannot_be_represented_exactly' => 'Конец окна Redis-квоты не представим точно',
    'ratelimiting.redis_rate_limit_requires_a_non_empty_scope_and' => 'Для Redis rate-limit требуются непустой scope и положительный представимый ioTimeoutMs',
    'ratelimiting.redis_backend_requires_normal_mode' => 'Redis backend требует обычный режим, отсутствие serializer/compression и автоматических повторов',
];
