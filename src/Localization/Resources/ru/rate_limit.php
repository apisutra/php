<?php

declare(strict_types=1);

return [
    'rate_limit.cooldown_publication_unconfirmed' => 'Публикация cooldown не подтверждена; сохранена исходная ошибка записи ответа.',
    'rate_limit.invalid_cooldown_duration' => 'Срок cooldown должен быть положительным и представимым в миллисекундах.',
    'rate_limit.invalid_cooldown_scope' => 'Область Redis cooldown не должна быть пустой.',
    'rate_limit.cooldown_resource_missing' => 'Ресурс Lua для cooldown недоступен.',
    'rate_limit.cooldown_backend_failed' => 'Не удалось выполнить операцию хранилища cooldown.',
    'rate_limit.cooldown_storage' => 'Операция хранилища cooldown.',
    'rate_limit.server_cooldown_active' => 'Действует запрет отправки по Retry-After сервера.',
    'rate_limit.invalid_cooldown_config' => 'Группа и identity cooldown не должны быть пустыми; предел дополнительного ожидания должен быть неотрицательным и представимым.',
    'rate_limit.cooldown_identity_failed' => 'Не удалось определить идентичность для cooldown.',
    'rate_limit.cooldown_identity_unavailable' => 'Cooldown пропущен: стабильная идентичность авторизации неизвестна.',
    'rate_limit.cooldown_extended' => 'Серверный запрет отправки продлён.',
    'rate_limit.cooldown_wait' => 'Ожидание повтора или серверного запрета.',
];
